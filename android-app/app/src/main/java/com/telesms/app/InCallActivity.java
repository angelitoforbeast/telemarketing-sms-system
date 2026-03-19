package com.telesms.app;

import android.Manifest;
import android.app.Activity;
import android.app.KeyguardManager;
import android.content.Context;
import android.content.pm.PackageManager;
import android.os.Build;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.telecom.Call;
import android.util.Log;
import android.view.View;
import android.view.WindowManager;
import android.widget.ImageButton;
import android.widget.TextView;

import androidx.core.app.ActivityCompat;

import java.util.Locale;

/**
 * In-call UI activity that shows during active calls.
 * Provides controls for answer, reject, hangup, mute, speaker, and hold.
 * Also manages call recording via CallManager.
 */
public class InCallActivity extends Activity implements CallManager.CallStateListener {

    private static final String TAG = "InCallActivity";
    private static final int PERMISSION_REQUEST_RECORD_AUDIO = 200;

    // UI Elements
    private TextView tvCallerName;
    private TextView tvPhoneNumber;
    private TextView tvCallStatus;
    private TextView tvCallDuration;
    private TextView tvRecordingStatus;

    private ImageButton btnAnswer;
    private ImageButton btnReject;
    private ImageButton btnHangup;
    private ImageButton btnMute;
    private ImageButton btnSpeaker;
    private ImageButton btnHold;

    private View incomingCallControls;
    private View activeCallControls;

    // State
    private boolean isMuted = false;
    private boolean isSpeakerOn = false;
    private boolean isOnHold = false;
    private boolean recordingStarted = false;

    // Timer
    private Handler timerHandler = new Handler(Looper.getMainLooper());
    private Runnable timerRunnable;
    private long callStartTime;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Show over lock screen
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O_MR1) {
            setShowWhenLocked(true);
            setTurnScreenOn(true);
            KeyguardManager keyguardManager = (KeyguardManager) getSystemService(Context.KEYGUARD_SERVICE);
            if (keyguardManager != null) {
                keyguardManager.requestDismissKeyguard(this, null);
            }
        } else {
            getWindow().addFlags(
                    WindowManager.LayoutParams.FLAG_SHOW_WHEN_LOCKED |
                    WindowManager.LayoutParams.FLAG_DISMISS_KEYGUARD |
                    WindowManager.LayoutParams.FLAG_TURN_SCREEN_ON |
                    WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON
            );
        }

        setContentView(getLayoutResourceId());
        initViews();
        setupListeners();

        // Register for call state updates
        CallManager.getInstance().addListener(this);

        // Update UI based on call type
        String callType = getIntent().getStringExtra("call_type");
        if ("incoming".equals(callType)) {
            showIncomingCallUI();
        } else {
            showOutgoingCallUI();
        }

        updatePhoneNumberDisplay();
    }

    @Override
    protected void onDestroy() {
        super.onDestroy();
        CallManager.getInstance().removeListener(this);
        timerHandler.removeCallbacksAndMessages(null);
    }

    @Override
    protected void onNewIntent(android.content.Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        String callType = intent.getStringExtra("call_type");
        if ("incoming".equals(callType)) {
            showIncomingCallUI();
        } else {
            showOutgoingCallUI();
        }
        updatePhoneNumberDisplay();
    }

    // ==================== Layout Setup ====================

    /**
     * Get layout resource ID. Uses a programmatic layout to avoid
     * needing XML layout files (can be replaced with XML later).
     */
    private int getLayoutResourceId() {
        // We'll use R.layout.activity_incall if it exists
        int layoutId = getResources().getIdentifier("activity_incall", "layout", getPackageName());
        if (layoutId != 0) return layoutId;

        // Fallback: create programmatic layout
        return 0; // Will use programmatic layout in initViews
    }

    private void initViews() {
        int layoutId = getLayoutResourceId();

        if (layoutId != 0) {
            // XML layout exists - find views by ID
            tvCallerName = findViewById(getResId("tv_caller_name"));
            tvPhoneNumber = findViewById(getResId("tv_phone_number"));
            tvCallStatus = findViewById(getResId("tv_call_status"));
            tvCallDuration = findViewById(getResId("tv_call_duration"));
            tvRecordingStatus = findViewById(getResId("tv_recording_status"));

            btnAnswer = findViewById(getResId("btn_answer"));
            btnReject = findViewById(getResId("btn_reject"));
            btnHangup = findViewById(getResId("btn_hangup"));
            btnMute = findViewById(getResId("btn_mute"));
            btnSpeaker = findViewById(getResId("btn_speaker"));
            btnHold = findViewById(getResId("btn_hold"));

            incomingCallControls = findViewById(getResId("incoming_call_controls"));
            activeCallControls = findViewById(getResId("active_call_controls"));
        } else {
            // Create programmatic layout
            createProgrammaticLayout();
        }
    }

    private int getResId(String name) {
        return getResources().getIdentifier(name, "id", getPackageName());
    }

    /**
     * Create a simple programmatic layout as fallback.
     * This ensures the app works even without XML layout files.
     */
    private void createProgrammaticLayout() {
        android.widget.LinearLayout root = new android.widget.LinearLayout(this);
        root.setOrientation(android.widget.LinearLayout.VERTICAL);
        root.setGravity(android.view.Gravity.CENTER_HORIZONTAL);
        root.setBackgroundColor(0xFF1A1A2E);
        root.setPadding(48, 100, 48, 48);

        // Caller info section
        tvCallerName = new TextView(this);
        tvCallerName.setTextSize(28);
        tvCallerName.setTextColor(0xFFFFFFFF);
        tvCallerName.setGravity(android.view.Gravity.CENTER);
        root.addView(tvCallerName);

        tvPhoneNumber = new TextView(this);
        tvPhoneNumber.setTextSize(18);
        tvPhoneNumber.setTextColor(0xFFB0B0B0);
        tvPhoneNumber.setGravity(android.view.Gravity.CENTER);
        tvPhoneNumber.setPadding(0, 8, 0, 16);
        root.addView(tvPhoneNumber);

        tvCallStatus = new TextView(this);
        tvCallStatus.setTextSize(16);
        tvCallStatus.setTextColor(0xFF4CAF50);
        tvCallStatus.setGravity(android.view.Gravity.CENTER);
        tvCallStatus.setPadding(0, 8, 0, 8);
        root.addView(tvCallStatus);

        tvCallDuration = new TextView(this);
        tvCallDuration.setTextSize(24);
        tvCallDuration.setTextColor(0xFFFFFFFF);
        tvCallDuration.setGravity(android.view.Gravity.CENTER);
        tvCallDuration.setPadding(0, 8, 0, 24);
        tvCallDuration.setVisibility(View.GONE);
        root.addView(tvCallDuration);

        tvRecordingStatus = new TextView(this);
        tvRecordingStatus.setTextSize(14);
        tvRecordingStatus.setTextColor(0xFFFF4444);
        tvRecordingStatus.setGravity(android.view.Gravity.CENTER);
        tvRecordingStatus.setPadding(0, 4, 0, 32);
        tvRecordingStatus.setVisibility(View.GONE);
        root.addView(tvRecordingStatus);

        // Spacer
        View spacer = new View(this);
        android.widget.LinearLayout.LayoutParams spacerParams =
                new android.widget.LinearLayout.LayoutParams(0, 0, 1.0f);
        spacer.setLayoutParams(spacerParams);
        root.addView(spacer);

        // Incoming call controls (Answer + Reject)
        incomingCallControls = new android.widget.LinearLayout(this);
        ((android.widget.LinearLayout) incomingCallControls).setOrientation(
                android.widget.LinearLayout.HORIZONTAL);
        ((android.widget.LinearLayout) incomingCallControls).setGravity(
                android.view.Gravity.CENTER);
        incomingCallControls.setVisibility(View.GONE);

        btnAnswer = createCircleButton(0xFF4CAF50, "Answer");
        btnReject = createCircleButton(0xFFF44336, "Reject");

        android.widget.LinearLayout.LayoutParams btnParams =
                new android.widget.LinearLayout.LayoutParams(160, 160);
        btnParams.setMargins(32, 0, 32, 0);

        ((android.widget.LinearLayout) incomingCallControls).addView(btnAnswer, btnParams);
        ((android.widget.LinearLayout) incomingCallControls).addView(btnReject, btnParams);
        root.addView(incomingCallControls);

        // Active call controls (Mute, Speaker, Hold)
        activeCallControls = new android.widget.LinearLayout(this);
        ((android.widget.LinearLayout) activeCallControls).setOrientation(
                android.widget.LinearLayout.VERTICAL);
        ((android.widget.LinearLayout) activeCallControls).setGravity(
                android.view.Gravity.CENTER);
        activeCallControls.setVisibility(View.GONE);

        // Row 1: Mute, Speaker, Hold
        android.widget.LinearLayout row1 = new android.widget.LinearLayout(this);
        row1.setOrientation(android.widget.LinearLayout.HORIZONTAL);
        row1.setGravity(android.view.Gravity.CENTER);

        btnMute = createCircleButton(0xFF555555, "Mute");
        btnSpeaker = createCircleButton(0xFF555555, "Speaker");
        btnHold = createCircleButton(0xFF555555, "Hold");

        android.widget.LinearLayout.LayoutParams smallBtnParams =
                new android.widget.LinearLayout.LayoutParams(130, 130);
        smallBtnParams.setMargins(24, 0, 24, 0);

        row1.addView(btnMute, smallBtnParams);
        row1.addView(btnSpeaker, smallBtnParams);
        row1.addView(btnHold, smallBtnParams);
        ((android.widget.LinearLayout) activeCallControls).addView(row1);

        // Row 2: Hangup button
        btnHangup = createCircleButton(0xFFF44336, "End");
        android.widget.LinearLayout.LayoutParams hangupParams =
                new android.widget.LinearLayout.LayoutParams(160, 160);
        hangupParams.setMargins(0, 40, 0, 32);
        ((android.widget.LinearLayout) activeCallControls).addView(btnHangup, hangupParams);

        root.addView(activeCallControls);

        setContentView(root);
    }

    private ImageButton createCircleButton(int color, String contentDesc) {
        ImageButton btn = new ImageButton(this);
        android.graphics.drawable.GradientDrawable shape = new android.graphics.drawable.GradientDrawable();
        shape.setShape(android.graphics.drawable.GradientDrawable.OVAL);
        shape.setColor(color);
        btn.setBackground(shape);
        btn.setContentDescription(contentDesc);
        btn.setScaleType(ImageButton.ScaleType.CENTER_INSIDE);

        // Set icon based on content description
        int iconRes = getIconResource(contentDesc);
        if (iconRes != 0) {
            btn.setImageResource(iconRes);
        }
        btn.setColorFilter(0xFFFFFFFF);

        return btn;
    }

    private int getIconResource(String name) {
        switch (name) {
            case "Answer":
                return android.R.drawable.sym_action_call;
            case "Reject":
            case "End":
                return android.R.drawable.ic_menu_call;
            case "Mute":
                return android.R.drawable.ic_lock_silent_mode;
            case "Speaker":
                return android.R.drawable.ic_lock_silent_mode_off;
            case "Hold":
                return android.R.drawable.ic_media_pause;
            default:
                return 0;
        }
    }

    // ==================== Button Listeners ====================

    private void setupListeners() {
        if (btnAnswer != null) {
            btnAnswer.setOnClickListener(v -> {
                CallManager.getInstance().answerCall();
                showActiveCallUI();
            });
        }

        if (btnReject != null) {
            btnReject.setOnClickListener(v -> {
                CallManager.getInstance().rejectCall();
                finishWithDelay();
            });
        }

        if (btnHangup != null) {
            btnHangup.setOnClickListener(v -> {
                CallManager.getInstance().hangupCall();
                finishWithDelay();
            });
        }

        if (btnMute != null) {
            btnMute.setOnClickListener(v -> {
                isMuted = !isMuted;
                TeleSMSInCallService service = TeleSMSInCallService.getInstance();
                if (service != null) {
                    service.toggleMute(isMuted);
                }
                updateMuteButton();
            });
        }

        if (btnSpeaker != null) {
            btnSpeaker.setOnClickListener(v -> {
                isSpeakerOn = !isSpeakerOn;
                TeleSMSInCallService service = TeleSMSInCallService.getInstance();
                if (service != null) {
                    service.setSpeaker(isSpeakerOn);
                }
                updateSpeakerButton();
            });
        }

        if (btnHold != null) {
            btnHold.setOnClickListener(v -> {
                isOnHold = !isOnHold;
                CallManager callManager = CallManager.getInstance();
                if (isOnHold) {
                    callManager.holdCall();
                } else {
                    callManager.unholdCall();
                }
                updateHoldButton();
            });
        }
    }

    // ==================== UI State ====================

    private void showIncomingCallUI() {
        if (tvCallStatus != null) tvCallStatus.setText("Incoming Call");
        if (incomingCallControls != null) incomingCallControls.setVisibility(View.VISIBLE);
        if (activeCallControls != null) activeCallControls.setVisibility(View.GONE);
        if (tvCallDuration != null) tvCallDuration.setVisibility(View.GONE);
    }

    private void showOutgoingCallUI() {
        if (tvCallStatus != null) tvCallStatus.setText("Calling...");
        if (incomingCallControls != null) incomingCallControls.setVisibility(View.GONE);
        if (activeCallControls != null) activeCallControls.setVisibility(View.VISIBLE);
        if (tvCallDuration != null) tvCallDuration.setVisibility(View.GONE);
    }

    private void showActiveCallUI() {
        if (tvCallStatus != null) tvCallStatus.setText("Connected");
        if (incomingCallControls != null) incomingCallControls.setVisibility(View.GONE);
        if (activeCallControls != null) activeCallControls.setVisibility(View.VISIBLE);
        if (tvCallDuration != null) {
            tvCallDuration.setVisibility(View.VISIBLE);
            startTimer();
        }

        // Start recording when call becomes active
        startCallRecording();
    }

    private void updatePhoneNumberDisplay() {
        CallManager callManager = CallManager.getInstance();
        String phoneNumber = callManager.getCurrentPhoneNumber();
        if (tvPhoneNumber != null && phoneNumber != null) {
            tvPhoneNumber.setText(phoneNumber);
        }
        if (tvCallerName != null) {
            tvCallerName.setText("TeleSMS Call");
        }
    }

    private void updateMuteButton() {
        if (btnMute != null) {
            android.graphics.drawable.GradientDrawable shape =
                    new android.graphics.drawable.GradientDrawable();
            shape.setShape(android.graphics.drawable.GradientDrawable.OVAL);
            shape.setColor(isMuted ? 0xFF2196F3 : 0xFF555555);
            btnMute.setBackground(shape);
        }
    }

    private void updateSpeakerButton() {
        if (btnSpeaker != null) {
            android.graphics.drawable.GradientDrawable shape =
                    new android.graphics.drawable.GradientDrawable();
            shape.setShape(android.graphics.drawable.GradientDrawable.OVAL);
            shape.setColor(isSpeakerOn ? 0xFF2196F3 : 0xFF555555);
            btnSpeaker.setBackground(shape);
        }
    }

    private void updateHoldButton() {
        if (btnHold != null) {
            android.graphics.drawable.GradientDrawable shape =
                    new android.graphics.drawable.GradientDrawable();
            shape.setShape(android.graphics.drawable.GradientDrawable.OVAL);
            shape.setColor(isOnHold ? 0xFFFF9800 : 0xFF555555);
            btnHold.setBackground(shape);
        }
        if (tvCallStatus != null) {
            tvCallStatus.setText(isOnHold ? "On Hold" : "Connected");
        }
    }

    // ==================== Call Recording ====================

    private void startCallRecording() {
        if (recordingStarted) return;

        // Check RECORD_AUDIO permission
        if (ActivityCompat.checkSelfPermission(this, Manifest.permission.RECORD_AUDIO)
                != PackageManager.PERMISSION_GRANTED) {
            ActivityCompat.requestPermissions(this,
                    new String[]{Manifest.permission.RECORD_AUDIO},
                    PERMISSION_REQUEST_RECORD_AUDIO);
            return;
        }

        recordingStarted = true;
        CallManager.getInstance().startRecording(this);

        if (tvRecordingStatus != null) {
            tvRecordingStatus.setVisibility(View.VISIBLE);
            tvRecordingStatus.setText("\u25CF REC");
        }
    }

    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        if (requestCode == PERMISSION_REQUEST_RECORD_AUDIO) {
            if (grantResults.length > 0 && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                startCallRecording();
            } else {
                Log.w(TAG, "RECORD_AUDIO permission denied");
                if (tvRecordingStatus != null) {
                    tvRecordingStatus.setVisibility(View.VISIBLE);
                    tvRecordingStatus.setText("Recording permission denied");
                    tvRecordingStatus.setTextColor(0xFFFF9800);
                }
            }
        }
    }

    // ==================== Timer ====================

    private void startTimer() {
        callStartTime = System.currentTimeMillis();
        timerRunnable = new Runnable() {
            @Override
            public void run() {
                long elapsed = (System.currentTimeMillis() - callStartTime) / 1000;
                String duration = String.format(Locale.US, "%02d:%02d",
                        elapsed / 60, elapsed % 60);
                if (tvCallDuration != null) {
                    tvCallDuration.setText(duration);
                }
                timerHandler.postDelayed(this, 1000);
            }
        };
        timerHandler.post(timerRunnable);
    }

    private void stopTimer() {
        timerHandler.removeCallbacksAndMessages(null);
    }

    // ==================== CallManager.CallStateListener ====================

    @Override
    public void onCallStateChanged(int state, String phoneNumber) {
        runOnUiThread(() -> {
            switch (state) {
                case Call.STATE_ACTIVE:
                    showActiveCallUI();
                    break;

                case Call.STATE_HOLDING:
                    if (tvCallStatus != null) tvCallStatus.setText("On Hold");
                    break;

                case Call.STATE_DIALING:
                case Call.STATE_CONNECTING:
                    if (tvCallStatus != null) tvCallStatus.setText("Calling...");
                    break;

                case Call.STATE_RINGING:
                    showIncomingCallUI();
                    break;

                case Call.STATE_DISCONNECTED:
                case Call.STATE_DISCONNECTING:
                    stopTimer();
                    if (tvCallStatus != null) tvCallStatus.setText("Call Ended");
                    if (tvRecordingStatus != null) tvRecordingStatus.setVisibility(View.GONE);
                    finishWithDelay();
                    break;
            }
        });
    }

    @Override
    public void onRecordingStateChanged(boolean recording) {
        runOnUiThread(() -> {
            if (tvRecordingStatus != null) {
                if (recording) {
                    tvRecordingStatus.setVisibility(View.VISIBLE);
                    tvRecordingStatus.setText("\u25CF REC");
                    tvRecordingStatus.setTextColor(0xFFFF4444);
                } else {
                    tvRecordingStatus.setVisibility(View.GONE);
                }
            }
        });
    }

    // ==================== Utilities ====================

    private void finishWithDelay() {
        timerHandler.postDelayed(this::finish, 1500);
    }

    @Override
    public void onBackPressed() {
        // Don't allow back button to close the in-call screen
        // User must use hangup/reject buttons
    }
}
