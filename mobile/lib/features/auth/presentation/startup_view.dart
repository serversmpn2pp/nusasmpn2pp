import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/shared/widgets/nusa_illustrations.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';

class StartupView extends ConsumerWidget {
  const StartupView({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final error = auth.error;

    return Scaffold(
      backgroundColor: NusaColors.primaryDark,
      body: Stack(
        fit: StackFit.expand,
        children: [
          const NusaSplashDecoration(),
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  NusaColors.primary.withValues(alpha: 0.24),
                  Colors.transparent,
                  NusaColors.primary.withValues(alpha: 0.16),
                ],
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 28),
              child: Column(
                children: [
                  const Spacer(flex: 3),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.05),
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFF2C9FF1)
                              .withValues(alpha: 0.28),
                          blurRadius: 36,
                          spreadRadius: 8,
                        ),
                      ],
                    ),
                    child: const NusaLogo(size: 166),
                  ),
                  const SizedBox(height: 22),
                  const Text(
                    'NUSA',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 52,
                      height: 1,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 5,
                    ),
                  ),
                  const SizedBox(height: 10),
                  const FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      'SMP Negeri 2 Padang Panjang',
                      style: TextStyle(
                        color: NusaColors.accent,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const Spacer(flex: 2),
                  if (error == null) ...[
                    const SizedBox(
                      width: 42,
                      child: LinearProgressIndicator(
                        minHeight: 4,
                        borderRadius: BorderRadius.all(Radius.circular(8)),
                        backgroundColor: Color(0x553E91D0),
                      ),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Memuat...',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ] else ...[
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Text(
                        error is AppException
                            ? error.message
                            : 'Sesi NUSA belum dapat diperiksa.',
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.white),
                      ),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton.icon(
                      onPressed: () => ref.invalidate(authControllerProvider),
                      icon: const Icon(Icons.refresh),
                      label: const Text('Coba lagi'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.white,
                        side: const BorderSide(color: Colors.white54),
                      ),
                    ),
                  ],
                  const Spacer(),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
