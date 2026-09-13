import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

@pragma('vm:entry-point')
Future<void> nusaFirebaseMessagingBackgroundHandler(
  RemoteMessage message,
) async {
  if (Firebase.apps.isEmpty) {
    await Firebase.initializeApp();
  }
}

Future<bool> initializeNusaFirebase() async {
  try {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(
      nusaFirebaseMessagingBackgroundHandler,
    );
    return true;
  } catch (error) {
    debugPrint(
      'Firebase belum dikonfigurasi; aplikasi berjalan tanpa push notification: '
      '$error',
    );
    return false;
  }
}
