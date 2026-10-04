import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:nusa/core/config/app_config.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';

String? humasMeetingToken(String raw, Uri apiBase) {
  final uri = Uri.tryParse(raw.trim());
  if (uri == null ||
      !['https', 'http'].contains(uri.scheme) ||
      uri.userInfo.isNotEmpty ||
      uri.host.toLowerCase() != apiBase.host.toLowerCase() ||
      uri.query.isNotEmpty ||
      uri.fragment.isNotEmpty) {
    return null;
  }
  final match = RegExp(r'^/presensi-pertemuan/([A-Za-z0-9]{64})/?$')
      .firstMatch(uri.path);
  return match?.group(1);
}

class HumasScanView extends ConsumerStatefulWidget {
  const HumasScanView({super.key});
  @override
  ConsumerState<HumasScanView> createState() => _HumasScanViewState();
}

class _HumasScanViewState extends ConsumerState<HumasScanView>
    with WidgetsBindingObserver {
  final _camera = MobileScannerController(
    formats: const [BarcodeFormat.qrCode],
    detectionSpeed: DetectionSpeed.noDuplicates,
  );
  bool _busy = false;
  HumasData? _invitation;
  String? _token;
  String? _error;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    unawaited(_camera.dispose());
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (!_camera.value.isInitialized || !_camera.value.hasCameraPermission) {
      return;
    }
    if (state == AppLifecycleState.resumed && !_busy && _invitation == null) {
      unawaited(_restartCamera());
    }
    if (state == AppLifecycleState.inactive) {
      unawaited(_stopCamera());
    }
  }

  Future<void> _restartCamera() async {
    try {
      await _camera.start();
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = 'Kamera belum tersedia. Periksa izin kamera NUSA di pengaturan HP.',
        );
      }
    }
  }

  Future<void> _stopCamera() async {
    try {
      await _camera.stop();
    } catch (_) {
      // Camera interruption must never crash a meeting or reveal private data.
    }
  }

  Future<void> _scan(String raw) async {
    if (_busy || _invitation != null) return;
    final token = humasMeetingToken(
      raw,
      ref.read(appConfigProvider).apiBaseUri,
    );
    if (token == null) {
      setState(() => _error = 'QR ini bukan QR pertemuan NUSA.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _camera.stop();
      final invitation = await ref
          .read(humasRepositoryProvider)
          .get('pertemuan-saya/scan/$token');
      if (mounted) {
        setState(() {
          _invitation = invitation;
          _token = token;
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = humasError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm() async {
    if (_busy || _token == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = await ref
          .read(humasRepositoryProvider)
          .send('pertemuan-saya/scan/$_token/hadir', {});
      if (mounted) setState(() => _invitation = data);
    } catch (error) {
      if (mounted) setState(() => _error = humasError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final agenda = humasMap(_invitation?['agenda']);
    final attendance = humasMap(_invitation?['kehadiran']);
    return Scaffold(
      appBar: AppBar(title: const Text('Scan QR Pertemuan')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            const Text(
              'Arahkan kamera ke QR pertemuan sekolah. Periksa undangan sebelum mengonfirmasi kehadiran.',
            ),
            const SizedBox(height: 16),
            if (_invitation == null)
              ClipRRect(
                borderRadius: BorderRadius.circular(18),
                child: AspectRatio(
                  aspectRatio: 1,
                  child: MobileScanner(
                    controller: _camera,
                    onDetect: (capture) {
                      final raw = capture.barcodes.firstOrNull?.rawValue;
                      if (raw != null) unawaited(_scan(raw));
                    },
                    errorBuilder: (_, _) => const Center(
                      child: Text(
                        'Kamera belum tersedia. Periksa izin kamera NUSA di pengaturan HP.',
                      ),
                    ),
                  ),
                ),
              ),
            if (_invitation != null)
              HumasCard(
                title: humasText(agenda['judul']),
                subtitle:
                    '${humasDate(agenda['waktu_mulai'])}\n${humasText(agenda['tempat'])}',
                children: [
                  const SizedBox(height: 12),
                  HumasBadge(humasText(attendance['status_label'])),
                ],
              ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 16),
                child: Text(_error!),
              ),
            if (_busy) const Center(child: CircularProgressIndicator()),
            if (_invitation != null &&
                agenda['presensi_dibuka'] == true &&
                attendance['status_kehadiran'] == 'belum_dicatat')
              FilledButton(
                onPressed: _busy ? null : _confirm,
                child: const Text('Konfirmasi Saya Hadir'),
              ),
            if (!_busy)
              TextButton(
                onPressed: () async {
                  setState(() {
                    _invitation = null;
                    _error = null;
                    _token = null;
                  });
                  await _restartCamera();
                },
                child: const Text('Scan ulang'),
              ),
          ],
        ),
      ),
    );
  }
}
