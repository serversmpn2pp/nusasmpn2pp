import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/app/app.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/core/notifications/firebase_bootstrap.dart';
import 'package:nusa/features/push_notifications/application/push_device_service.dart';

Future<void> bootstrap() async {
  WidgetsFlutterBinding.ensureInitialized();

  final config = AppConfig.fromEnvironment();
  final firebaseAvailable = await initializeNusaFirebase();

  runApp(
    ProviderScope(
      overrides: [
        appConfigProvider.overrideWithValue(config),
        firebaseMessagingAvailableProvider.overrideWithValue(firebaseAvailable),
      ],
      child: const NusaApp(),
    ),
  );
}
