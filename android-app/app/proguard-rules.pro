# OkHttp
-dontwarn okhttp3.**
-keep class okhttp3.** { *; }
-dontwarn okio.**
-keep class okio.** { *; }

# Keep WebView JavaScript interface
-keepclassmembers class com.telesms.app.TeleSMSJSBridge {
    public *;
}
