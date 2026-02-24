package com.telesms.app;

import android.annotation.SuppressLint;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Bundle;
import android.util.Log;
import android.view.KeyEvent;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import android.widget.ProgressBar;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.appcompat.app.AlertDialog;
import androidx.appcompat.app.AppCompatActivity;

public class MainActivity extends AppCompatActivity {

    private static final String TAG = "MainActivity";
    private WebView webView;
    private ProgressBar progressBar;
    private TeleSMSJSBridge jsBridge;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Check if server URL is configured
        SharedPreferences prefs = getSharedPreferences("telesms", MODE_PRIVATE);
        String serverUrl = prefs.getString("server_url", "");

        if (serverUrl.isEmpty()) {
            // Show server config screen first
            Intent intent = new Intent(this, ServerConfigActivity.class);
            startActivity(intent);
            finish();
            return;
        }

        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);

        setupWebView();
        loadServerUrl(serverUrl);
    }

    @SuppressLint("SetJavaScriptEnabled")
    private void setupWebView() {
        WebSettings settings = webView.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setAllowFileAccess(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_ALWAYS_ALLOW);
        settings.setUserAgentString(settings.getUserAgentString() + " TeleSMS-Android/1.0");

        // Enable cookies
        CookieManager cookieManager = CookieManager.getInstance();
        cookieManager.setAcceptCookie(true);
        cookieManager.setAcceptThirdPartyCookies(webView, true);

        // Add JavaScript bridge
        jsBridge = new TeleSMSJSBridge(this);
        webView.addJavascriptInterface(jsBridge, "TeleSMS");

        // WebView client — handle navigation
        webView.setWebViewClient(new WebViewClient() {
            @Override
            public boolean shouldOverrideUrlLoading(WebView view, WebResourceRequest request) {
                Uri uri = request.getUrl();
                String scheme = uri.getScheme();

                // Handle tel: links — use our native bridge instead
                if ("tel".equals(scheme)) {
                    String phoneNumber = uri.getSchemeSpecificPart();
                    // Get shipment context from the page
                    view.evaluateJavascript(
                            "(function() { " +
                                    "var el = document.querySelector('[data-shipment-id]'); " +
                                    "return el ? el.getAttribute('data-shipment-id') : ''; " +
                                    "})()",
                            shipmentId -> {
                                String sid = shipmentId.replace("\"", "");
                                jsBridge.makeCall(phoneNumber, sid, "");
                            });
                    return true;
                }

                // Handle mailto: links
                if ("mailto".equals(scheme)) {
                    Intent intent = new Intent(Intent.ACTION_SENDTO, uri);
                    startActivity(intent);
                    return true;
                }

                // Stay in WebView for same-origin URLs
                String serverUrl = getSharedPreferences("telesms", MODE_PRIVATE)
                        .getString("server_url", "");
                if (uri.toString().startsWith(serverUrl)) {
                    return false; // Load in WebView
                }

                // Open external URLs in browser
                Intent intent = new Intent(Intent.ACTION_VIEW, uri);
                startActivity(intent);
                return true;
            }

            @Override
            public void onPageStarted(WebView view, String url, Bitmap favicon) {
                progressBar.setVisibility(View.VISIBLE);
            }

            @Override
            public void onPageFinished(WebView view, String url) {
                progressBar.setVisibility(View.GONE);

                // Inject helper JS to detect TeleSMS app and sync cookies
                view.evaluateJavascript(
                        "(function() {" +
                                "  window.isTeleSMSApp = true;" +
                                "  if (typeof TeleSMS !== 'undefined') {" +
                                "    TeleSMS.setAuthCookie(document.cookie);" +
                                "  }" +
                                // Inject CSS to hide browser-only elements
                                "  var style = document.createElement('style');" +
                                "  style.textContent = '.hide-in-app { display: none !important; }';" +
                                "  document.head.appendChild(style);" +
                                "})()",
                        null);

                // Inject call button override for the call form page
                if (url.contains("/telemarketing/call/")) {
                    injectCallButtonOverride(view);
                }
            }
        });

        // Chrome client — handle progress
        webView.setWebChromeClient(new WebChromeClient() {
            @Override
            public void onProgressChanged(WebView view, int newProgress) {
                progressBar.setProgress(newProgress);
                if (newProgress >= 100) {
                    progressBar.setVisibility(View.GONE);
                }
            }
        });
    }

    /**
     * Override the call button on the call form page to use native calling
     * with recording tracking instead of simple tel: link.
     */
    private void injectCallButtonOverride(WebView view) {
        view.evaluateJavascript(
                "(function() {" +
                        "  // Find call buttons and override them" +
                        "  var callBtns = document.querySelectorAll('a[href^=\"tel:\"]');" +
                        "  callBtns.forEach(function(btn) {" +
                        "    btn.addEventListener('click', function(e) {" +
                        "      e.preventDefault();" +
                        "      var phone = btn.href.replace('tel:', '');" +
                        "      var shipmentEl = document.querySelector('[data-shipment-id]');" +
                        "      var shipmentId = shipmentEl ? shipmentEl.getAttribute('data-shipment-id') : '';" +
                        "      if (typeof TeleSMS !== 'undefined') {" +
                        "        TeleSMS.makeCall(phone, shipmentId, '');" +
                        "      }" +
                        "    });" +
                        "  });" +
                        "" +
                        "  // Add recording status indicator" +
                        "  var callSection = document.querySelector('.call-actions, .call-info');" +
                        "  if (callSection) {" +
                        "    var indicator = document.createElement('div');" +
                        "    indicator.id = 'recording-status';" +
                        "    indicator.className = 'mt-2 text-sm text-gray-500';" +
                        "    indicator.innerHTML = '📱 TeleSMS App — Call recording enabled';" +
                        "    callSection.appendChild(indicator);" +
                        "  }" +
                        "" +
                        "  // Poll for call status after returning from call" +
                        "  var pollInterval = setInterval(function() {" +
                        "    if (typeof TeleSMS !== 'undefined') {" +
                        "      var status = JSON.parse(TeleSMS.getCallStatus());" +
                        "      var indicator = document.getElementById('recording-status');" +
                        "      if (indicator) {" +
                        "        if (status.pendingUpload) {" +
                        "          indicator.innerHTML = '⏳ Uploading call recording...';" +
                        "          indicator.className = 'mt-2 text-sm text-yellow-600';" +
                        "        } else if (status.lastUploadSuccess && status.callDuration > 0) {" +
                        "          indicator.innerHTML = '✅ Call recording uploaded (' + status.callDuration + 's)';" +
                        "          indicator.className = 'mt-2 text-sm text-green-600';" +
                        "          clearInterval(pollInterval);" +
                        "        } else if (status.callDuration > 0 && !status.lastUploadSuccess) {" +
                        "          indicator.innerHTML = '⚠️ No recording found (call: ' + status.callDuration + 's)';" +
                        "          indicator.className = 'mt-2 text-sm text-red-600';" +
                        "          clearInterval(pollInterval);" +
                        "        }" +
                        "      }" +
                        "    }" +
                        "  }, 2000);" +
                        "" +
                        "  // Stop polling after 60 seconds" +
                        "  setTimeout(function() { clearInterval(pollInterval); }, 60000);" +
                        "})()",
                null);
    }

    private void loadServerUrl(String serverUrl) {
        webView.loadUrl(serverUrl + "/login");
    }

    @Override
    public boolean onKeyDown(int keyCode, KeyEvent event) {
        // Handle back button — go back in WebView history
        if (keyCode == KeyEvent.KEYCODE_BACK && webView.canGoBack()) {
            webView.goBack();
            return true;
        }
        return super.onKeyDown(keyCode, event);
    }

    @Override
    protected void onResume() {
        super.onResume();
        // When returning from a call, refresh the call status in WebView
        if (webView != null) {
            webView.evaluateJavascript(
                    "(function() {" +
                            "  if (typeof TeleSMS !== 'undefined' && window.location.href.includes('/telemarketing/call/')) {" +
                            "    var status = JSON.parse(TeleSMS.getCallStatus());" +
                            "    if (status.callDuration > 0) {" +
                            "      // Update the call timer if it exists" +
                            "      var timerEl = document.getElementById('call-timer') || document.getElementById('callTimer');" +
                            "      if (timerEl) {" +
                            "        var mins = Math.floor(status.callDuration / 60);" +
                            "        var secs = status.callDuration % 60;" +
                            "        timerEl.textContent = mins.toString().padStart(2, '0') + ':' + secs.toString().padStart(2, '0');" +
                            "      }" +
                            "      // Auto-fill duration if there's a hidden field" +
                            "      var durationInput = document.querySelector('input[name=\"call_duration\"]');" +
                            "      if (durationInput) durationInput.value = status.callDuration;" +
                            "    }" +
                            "  }" +
                            "})()",
                    null);
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, @NonNull String[] permissions,
                                            @NonNull int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode == 100 || requestCode == 101) {
            boolean allGranted = true;
            for (int result : grantResults) {
                if (result != PackageManager.PERMISSION_GRANTED) {
                    allGranted = false;
                    break;
                }
            }

            if (allGranted) {
                Toast.makeText(this, "All permissions granted!", Toast.LENGTH_SHORT).show();
            } else {
                Toast.makeText(this, "Some permissions were denied. Call recording may not work.",
                        Toast.LENGTH_LONG).show();
            }

            // Notify WebView about permission changes
            if (webView != null) {
                webView.evaluateJavascript(
                        "(function() {" +
                                "  if (typeof window.onPermissionsChanged === 'function') {" +
                                "    window.onPermissionsChanged();" +
                                "  }" +
                                "})()",
                        null);
            }
        }
    }
}
