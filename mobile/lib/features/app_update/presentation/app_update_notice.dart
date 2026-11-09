import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/app_update/data/app_update_gateway.dart';
import 'package:nusa/features/student_exam/application/exam_navigation_guard.dart';

class AppUpdateNotice extends ConsumerStatefulWidget {
  const AppUpdateNotice({required this.child, super.key});
  final Widget child;

  @override
  ConsumerState<AppUpdateNotice> createState() => _AppUpdateNoticeState();
}

class _AppUpdateNoticeState extends ConsumerState<AppUpdateNotice>
    with WidgetsBindingObserver {
  bool _checking = false;
  bool _opening = false;
  int? _version;
  int? _dismissedVersion;
  DateTime? _lastCheck;

  bool get _examActive => ref.read(examNavigationGuardProvider).active;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => unawaited(_check()));
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) unawaited(_check());
  }

  Future<void> _check() async {
    if (!mounted || _checking || _examActive) return;
    if (_lastCheck != null &&
        DateTime.now().difference(_lastCheck!) < const Duration(minutes: 10)) {
      return;
    }
    _checking = true;
    _lastCheck = DateTime.now();
    try {
      final version = await ref
          .read(appUpdateGatewayProvider)
          .availableVersion();
      if (mounted && !_examActive) setState(() => _version = version);
    } catch (_) {
      // Update checks must never prevent access to school services.
    } finally {
      _checking = false;
    }
  }

  Future<void> _openStore() async {
    if (_opening || _examActive) return;
    setState(() => _opening = true);
    try {
      final opened = await ref.read(appUpdateGatewayProvider).openStore();
      if (!opened && mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Google Play belum dapat dibuka. Coba lagi nanti.'),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      if (_version != null && _version != _dismissedVersion && !_examActive)
        SafeArea(
          bottom: false,
          child: MaterialBanner(
            key: const Key('nusa-app-update-notice'),
            leading: const Icon(Icons.system_update_rounded),
            content: const Text(
              'Versi baru NUSA tersedia. Perbarui melalui Google Play untuk mendapatkan perbaikan dan fitur terbaru.',
            ),
            actions: [
              TextButton(
                onPressed: () => setState(() => _dismissedVersion = _version),
                child: const Text('Nanti'),
              ),
              FilledButton(
                onPressed: _opening ? null : _openStore,
                child: Text(_opening ? 'Membuka…' : 'Perbarui'),
              ),
            ],
          ),
        ),
      Expanded(child: widget.child),
    ],
  );
}
