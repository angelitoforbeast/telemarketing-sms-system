package com.telesms.app;

import android.content.Intent;
import android.content.SharedPreferences;
import android.os.Bundle;
import android.widget.Toast;

import androidx.appcompat.app.AppCompatActivity;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.textfield.TextInputEditText;

public class ServerConfigActivity extends AppCompatActivity {

    private TextInputEditText serverUrlInput;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_server_config);

        serverUrlInput = findViewById(R.id.serverUrlInput);
        MaterialButton saveButton = findViewById(R.id.saveButton);

        // Load existing URL if any
        SharedPreferences prefs = getSharedPreferences("telesms", MODE_PRIVATE);
        String existingUrl = prefs.getString("server_url", "");
        if (!existingUrl.isEmpty()) {
            serverUrlInput.setText(existingUrl);
        }

        saveButton.setOnClickListener(v -> {
            String url = serverUrlInput.getText().toString().trim();

            if (url.isEmpty()) {
                Toast.makeText(this, "Please enter a server URL", Toast.LENGTH_SHORT).show();
                return;
            }

            // Remove trailing slash
            if (url.endsWith("/")) {
                url = url.substring(0, url.length() - 1);
            }

            // Add https:// if no protocol
            if (!url.startsWith("http://") && !url.startsWith("https://")) {
                url = "https://" + url;
            }

            // Save URL
            prefs.edit().putString("server_url", url).apply();

            Toast.makeText(this, "Server URL saved!", Toast.LENGTH_SHORT).show();

            // Go to main activity
            Intent intent = new Intent(this, MainActivity.class);
            intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
            startActivity(intent);
            finish();
        });
    }
}
