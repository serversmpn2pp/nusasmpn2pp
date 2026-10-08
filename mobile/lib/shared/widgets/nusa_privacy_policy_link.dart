import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:url_launcher/url_launcher.dart';

typedef PrivacyPolicyOpener = Future<bool> Function(Uri uri);

final privacyPolicyOpenerProvider = Provider<PrivacyPolicyOpener>(
  (ref) =>
      (uri) => launchUrl(uri, mode: LaunchMode.externalApplication),
);

/// Tautan publik dapat digunakan sebelum login, tanpa mengirim token API.
class NusaPrivacyPolicyLink extends ConsumerStatefulWidget {
  const NusaPrivacyPolicyLink({super.key});

  @override
  ConsumerState<NusaPrivacyPolicyLink> createState() =>
      _NusaPrivacyPolicyLinkState();
}

class _NusaPrivacyPolicyLinkState extends ConsumerState<NusaPrivacyPolicyLink> {
  bool _opening = false;

  Future<void> _open() async {
    if (_opening) return;
    setState(() => _opening = true);
    try {
      final uri = ref.read(appConfigProvider).privacyPolicyUri;
      final opened = await ref.read(privacyPolicyOpenerProvider)(uri);
      if (!opened) _showFailure();
    } catch (_) {
      _showFailure();
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  void _showFailure() {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        const SnackBar(
          content: Text(
            'Halaman kebijakan privasi belum dapat dibuka. Silakan coba lagi melalui browser Anda.',
          ),
        ),
      );
  }

  @override
  Widget build(BuildContext context) {
    return TextButton.icon(
      onPressed: _opening ? null : _open,
      icon: _opening
          ? const SizedBox.square(
              dimension: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : const Icon(Icons.privacy_tip_outlined, size: 18),
      label: const Text('Kebijakan Privasi', textAlign: TextAlign.center),
      style: TextButton.styleFrom(minimumSize: const Size(0, 48)),
    );
  }
}
