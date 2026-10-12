import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:url_launcher/url_launcher.dart';

typedef AccountDeletionOpener = Future<bool> Function(Uri uri);

final accountDeletionOpenerProvider = Provider<AccountDeletionOpener>(
  (ref) =>
      (uri) => launchUrl(uri, mode: LaunchMode.externalApplication),
);

/// Membuka kanal permintaan publik, tanpa token atau identitas akun.
/// Tombol ini tidak menjalankan penghapusan data.
class NusaAccountDeletionLink extends ConsumerStatefulWidget {
  const NusaAccountDeletionLink({super.key});

  @override
  ConsumerState<NusaAccountDeletionLink> createState() =>
      _NusaAccountDeletionLinkState();
}

class _NusaAccountDeletionLinkState
    extends ConsumerState<NusaAccountDeletionLink> {
  bool _opening = false;

  Future<void> _open() async {
    if (_opening) return;
    setState(() => _opening = true);
    try {
      final uri = ref.read(appConfigProvider).accountDeletionUri;
      final opened = await ref.read(accountDeletionOpenerProvider)(uri);
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
            'Halaman permintaan penghapusan belum dapat dibuka. Silakan coba lagi melalui browser Anda.',
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
          : const Icon(Icons.manage_accounts_outlined, size: 18),
      label: const Text(
        'Permintaan Penghapusan Akun/Data',
        textAlign: TextAlign.center,
      ),
      style: TextButton.styleFrom(minimumSize: const Size(0, 48)),
    );
  }
}
