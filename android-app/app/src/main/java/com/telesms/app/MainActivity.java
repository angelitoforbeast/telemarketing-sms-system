package com.telesms.app;

import android.annotation.SuppressLint;
import android.app.role.RoleManager;
import android.content.Intent;
import android.content.SharedPreferences;
import android.content.pm.PackageManager;
import android.graphics.Bitmap;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.telecom.TelecomManager;
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
    private static final int REQUEST_DEFAULT_DIALER = 200;
    private static final String DEFAULT_SERVER_URL = "http://76.13.215.149";

    private WebView webView;
    private ProgressBar progressBar;
    private TeleSMSJSBridge jsBridge;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Hardcode server URL - no config screen needed
        SharedPreferences prefs = getSharedPreferences("telesms", MODE_PRIVATE);
        String serverUrl = prefs.getString("server_url", "");

        // Always ensure the correct URL is set
        if (serverUrl.isEmpty() || serverUrl.contains("https://")) {
            serverUrl = DEFAULT_SERVER_URL;
            prefs.edit().putString("server_url", serverUrl).apply();
        }

        setContentView(R.layout.activity_main);

        webView = findViewById(R.id.webView);
        progressBar = findViewById(R.id.progressBar);

        setupWebView();
        loadServerUrl(serverUrl);

        // Prompt to set as default dialer if not already
        checkAndPromptDefaultDialer();
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
        settings.setUserAgentString(settings.getUserAgentString() + " TeleSMS-Android/2.0");

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
                        .getString("server_url", DEFAULT_SERVER_URL);
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
                                "  window.teleSMSVersion = '2.0';" +
                                "  if (typeof TeleSMS !== 'undefined') {" +
                                "    TeleSMS.setAuthCookie(document.cookie);" +
                                "  }" +
                                // Inject CSS to hide browser-only elements
                                "  var style = document.createElement('style');" +
                                "  style.textContent = '.hide-in-app { display: none !important; }';" +
                                "  document.head.appendChild(style);" +
                                "})()",
                        null);

                // After login page, capture credentials for API token auth
                // This runs on every page load to capture the login form submission
                if (url.contains("/login") || url.contains("/dashboard") || url.contains("/telemarketing")) {
                    view.evaluateJavascript(
                            "(function() {" +
                                    // Intercept login form submission to capture credentials
                                    "  var loginForm = document.querySelector('form');" +
                                    "  if (loginForm && document.querySelector('input[type=email]')) {" +
                                    "    loginForm.addEventListener('submit', function() {" +
                                    "      var email = document.querySelector('input[type=email]').value;" +
                                    "      var pass = document.querySelector('input[type=password]').value;" +
                                    "      if (email && pass && typeof TeleSMS !== 'undefined') {" +
                                    "        TeleSMS.storeCredentials(email, pass);" +
                                    "      }" +
                                    "    });" +
                                    "  }" +
                                    // If already logged in (dashboard/telemarketing page), trigger token refresh
                                    "  if (typeof TeleSMS !== 'undefined' && !document.querySelector('input[type=email]')) {" +
                                    "    TeleSMS.refreshApiToken();" +
                                    "  }" +
                                    "})()",
                            null);
                }

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
                        "    var isDefault = typeof TeleSMS !== 'undefined' && TeleSMS.isDefaultDialer();" +
                        "    indicator.innerHTML = isDefault " +
                        "      ? '📱 TeleSMS Default Dialer — Built-in call recording active'" +
                        "      : '📱 TeleSMS App — Call recording via phone recorder';" +
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
                        "          var src = status.recordingAudioSource ? ' [' + status.recordingAudioSource + ']' : '';" +
                        "          indicator.innerHTML = '✅ Call recording uploaded (' + status.callDuration + 's)' + src;" +
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

    /**
     * Check if TeleSMS is the default dialer and prompt if not.
     * This is critical for built-in call recording to work.
     */
    private void checkAndPromptDefaultDialer() {
        try {
            TelecomManager telecomManager = (TelecomManager) getSystemService(TELECOM_SERVICE);
            if (telecomManager == null) return;

            String defaultDialer = telecomManager.getDefaultDialerPackage();
            if (getPackageName().equals(defaultDialer)) {
                Log.i(TAG, "TeleSMS is already the default dialer");
                return;
            }

            // Check if we've already asked recently (don't nag)
            SharedPreferences prefs = getSharedPreferences("telesms", MODE_PRIVATE);
            long lastPrompt = prefs.getLong("last_dialer_prompt", 0);
            long hoursSincePrompt = (System.currentTimeMillis() - lastPrompt) / (1000 * 60 * 60);

            // Only prompt once every 24 hours
            if (hoursSincePrompt < 24 && lastPrompt > 0) return;

            prefs.edit().putLong("last_dialer_prompt", System.currentTimeMillis()).apply();

            // Show explanation dialog first
            new AlertDialog.Builder(this)
                    .setTitle("Set TeleSMS as Default Phone App")
                    .setMessage("Para ma-record ang lahat ng calls, kailangan i-set ang TeleSMS " +
                            "bilang default phone app.\n\n" +
                            "Ito ay para sa built-in call recording na gumagana sa lahat ng phones.\n\n" +
                            "I-set as default?")
                    .setPositiveButton("Oo, I-set", (dialog, which) -> {
                        requestDefaultDialer();
                    })
                    .setNegativeButton("Mamaya", null)
                    .setCancelable(true)
                    .show();

        } catch (Exception e) {
            Log.e(TAG, "Error checking default dialer: " + e.getMessage());
        }
    }

    /**
     * Request to become the default dialer via system dialog.
     */
    private void requestDefaultDialer() {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                // Android 10+ uses RoleManager
                RoleManager roleManager = getSystemService(RoleManager.class);
                if (roleManager != null && roleManager.isRoleAvailable(RoleManager.ROLE_DIALER)
                        && !roleManager.isRoleHeld(RoleManager.ROLE_DIALER)) {
                    Intent intent = roleManager.createRequestRoleIntent(RoleManager.ROLE_DIALER);
                    startActivityForResult(intent, REQUEST_DEFAULT_DIALER);
                }
            } else {
                // Android 7-9 uses TelecomManager
                Intent intent = new Intent(TelecomManager.ACTION_CHANGE_DEFAULT_DIALER);
                intent.putExtra(TelecomManager.EXTRA_CHANGE_DEFAULT_DIALER_PACKAGE_NAME,
                        getPackageName());
                startActivityForResult(intent, REQUEST_DEFAULT_DIALER);
            }
        } catch (Exception e) {
            Log.e(TAG, "Error requesting default dialer: " + e.getMessage());
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);

        if (requestCode == REQUEST_DEFAULT_DIALER) {
            if (resultCode == RESULT_OK) {
                Toast.makeText(this,
                        "TeleSMS is now the default phone app! Call recording enabled.",
                        Toast.LENGTH_LONG).show();
                Log.i(TAG, "TeleSMS set as default dialer successfully");
                // After setting as default dialer, prompt for accessibility
                checkAndPromptAccessibility();
            } else {
                Toast.makeText(this,
                        "TeleSMS was not set as default. Recording may use phone's built-in recorder.",
                        Toast.LENGTH_LONG).show();
                Log.w(TAG, "User declined default dialer request");
                // Still prompt for accessibility - it helps even without default dialer
                checkAndPromptAccessibility();
            }
        }
    }

    /**
     * Check if TeleSMS Accessibility Service is enabled and prompt if not.
     * The accessibility service is critical for reliable call recording.
     */
    private void checkAndPromptAccessibility() {
        try {
            // Check if accessibility service is already enabled
            if (TeleSMSAccessibilityService.isEnabled(this)) {
                Log.i(TAG, "Accessibility service already enabled");
                return;
            }

            // Check if already enabled in system settings
            String enabledServices = android.provider.Settings.Secure.getString(
                    getContentResolver(),
                    android.provider.Settings.Secure.ENABLED_ACCESSIBILITY_SERVICES);
            if (enabledServices != null && enabledServices.contains(getPackageName())) {
                Log.i(TAG, "Accessibility service found in system settings");
                return;
            }

            // Don't nag - check if we prompted recently
            SharedPreferences prefs = getSharedPreferences("telesms", MODE_PRIVATE);
            long lastPrompt = prefs.getLong("last_accessibility_prompt", 0);
            long hoursSince = (System.currentTimeMillis() - lastPrompt) / (1000 * 60 * 60);
            if (hoursSince < 72 && lastPrompt > 0) return; // Only prompt every 3 days

            prefs.edit().putLong("last_accessibility_prompt", System.currentTimeMillis()).apply();

            new AlertDialog.Builder(this)
                    .setTitle("Enable Call Recording")
                    .setMessage("Para ma-record ang calls ng maayos, kailangan i-enable ang " +
                            "TeleSMS Accessibility Service.\n\n" +
                            "Steps:\n" +
                            "1. Tap 'I-enable' sa baba\n" +
                            "2. Hanapin ang 'TeleSMS'\n" +
                            "3. I-ON ang switch\n" +
                            "4. Tap 'Allow'\n\n" +
                            "Ito ay para sa call recording lang - hindi namin ginagamit para sa ibang bagay.")
                    .setPositiveButton("I-enable", (dialog, which) -> {
                        try {
                            Intent intent = new Intent(android.provider.Settings.ACTION_ACCESSIBILITY_SETTINGS);
                            startActivity(intent);
                        } catch (Exception e) {
                            Log.e(TAG, "Error opening accessibility settings: " + e.getMessage());
                            Toast.makeText(this, "Punta sa Settings > Accessibility > TeleSMS",
                                    Toast.LENGTH_LONG).show();
                        }
                    })
                    .setNegativeButton("Mamaya", null)
                    .setCancelable(true)
                    .show();

        } catch (Exception e) {
            Log.e(TAG, "Error checking accessibility: " + e.getMessage());
        }
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
