import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/login_activity/application/login_activity_controller.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart';
import 'package:nusa/features/login_activity/presentation/widgets/login_activity_components.dart';

class LoginAttemptDetailView extends ConsumerWidget {
  const LoginAttemptDetailView({required this.attemptId, super.key});

  final int attemptId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final detail = ref.watch(loginAttemptDetailProvider(attemptId));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Detail Aktivitas Login'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: detail.isLoading
                ? null
                : () => ref.invalidate(loginAttemptDetailProvider(attemptId)),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: detail.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _DetailError(
            message: _errorMessage(error),
            onRetry: () =>
                ref.invalidate(loginAttemptDetailProvider(attemptId)),
          ),
          data: (detail) => _DetailContent(attempt: detail.attempt),
        ),
      ),
    );
  }
}

class _DetailContent extends StatelessWidget {
  const _DetailContent({required this.attempt});

  final LoginAttempt attempt;

  @override
  Widget build(BuildContext context) {
    final user = attempt.user;
    return ListView(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
      children: [
        _ResultHeader(attempt: attempt),
        const SizedBox(height: 12),
        _SectionCard(
          icon: Icons.person_outline_rounded,
          title: 'Identitas Pengguna',
          child: user == null
              ? Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _UnknownAccountNotice(),
                    const SizedBox(height: 13),
                    _InformationRow(
                      label: 'Username',
                      value: attempt.username,
                      trailing: _CopyButton(
                        label: 'Username',
                        value: attempt.username,
                      ),
                    ),
                  ],
                )
              : Column(
                  children: [
                    _InformationRow(label: 'Nama', value: user.name),
                    const Divider(height: 20),
                    _InformationRow(
                      label: 'Username',
                      value: attempt.username,
                      trailing: _CopyButton(
                        label: 'Username',
                        value: attempt.username,
                      ),
                    ),
                    const Divider(height: 20),
                    _InformationRow(
                      label: 'Jenis akun',
                      value: user.accountType.label,
                    ),
                    const Divider(height: 20),
                    _InformationRow(label: 'Role', value: user.roleLabel),
                    const Divider(height: 20),
                    _InformationRow(
                      label: 'Status akun',
                      value: user.active ? 'Aktif' : 'Nonaktif',
                    ),
                  ],
                ),
        ),
        const SizedBox(height: 12),
        _SectionCard(
          icon: Icons.shield_outlined,
          title: 'Informasi Percobaan',
          child: Column(
            children: [
              _InformationRow(
                label: 'Hasil',
                value: attempt.success ? 'Berhasil' : 'Gagal',
              ),
              const Divider(height: 20),
              _InformationRow(
                label: 'Waktu',
                value: _fullDateTimeLabel(attempt.time),
              ),
              const Divider(height: 20),
              _InformationRow(
                label: 'Alamat IP',
                value: attempt.ipAddress ?? '-',
                trailing: attempt.ipAddress?.trim().isNotEmpty == true
                    ? _CopyButton(label: 'Alamat IP', value: attempt.ipAddress!)
                    : null,
              ),
              const Divider(height: 20),
              _InformationRow(
                label: 'Perangkat',
                value: attempt.device.label,
                leading: Icon(
                  deviceIcon(attempt.device.code),
                  color: NusaColors.primary,
                  size: 18,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        _SectionCard(
          icon: Icons.code_rounded,
          title: 'User Agent',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Container(
                padding: const EdgeInsets.all(13),
                decoration: BoxDecoration(
                  color: NusaColors.surfaceBlue.withValues(alpha: 0.65),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: SelectableText(
                  attempt.device.userAgent?.trim().isNotEmpty == true
                      ? attempt.device.userAgent!
                      : 'Tidak tersedia',
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 11.5,
                    height: 1.45,
                  ),
                ),
              ),
              if (attempt.device.userAgent?.trim().isNotEmpty == true) ...[
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerRight,
                  child: TextButton.icon(
                    key: const Key('copy-login-user-agent'),
                    onPressed: () =>
                        _copy(context, 'User agent', attempt.device.userAgent!),
                    icon: const Icon(Icons.copy_rounded, size: 18),
                    label: const Text('Salin user agent'),
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: attempt.success
                ? NusaColors.success.withValues(alpha: 0.08)
                : const Color(0xFFFFECEA),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: attempt.success
                  ? NusaColors.success.withValues(alpha: 0.25)
                  : const Color(0xFFFFC9C4),
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                attempt.success
                    ? Icons.verified_user_outlined
                    : Icons.warning_amber_rounded,
                color: attempt.success
                    ? NusaColors.success
                    : const Color(0xFFB42318),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  attempt.success
                      ? 'Percobaan ini berhasil masuk ke NUSA. Pastikan perangkat dan alamat IP dikenali.'
                      : 'Percobaan ini gagal. Periksa pola kegagalan berulang pada username, alamat IP, atau perangkat yang sama.',
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 11.5,
                    height: 1.45,
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ResultHeader extends StatelessWidget {
  const _ResultHeader({required this.attempt});

  final LoginAttempt attempt;

  @override
  Widget build(BuildContext context) {
    final color = attempt.success
        ? NusaColors.success
        : const Color(0xFFB42318);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
        ),
        borderRadius: BorderRadius.circular(19),
      ),
      child: Row(
        children: [
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.13),
              shape: BoxShape.circle,
            ),
            child: Icon(
              attempt.success ? Icons.login_rounded : Icons.gpp_bad_rounded,
              color: attempt.success ? NusaColors.accent : Colors.white,
              size: 29,
            ),
          ),
          const SizedBox(width: 13),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Percobaan Login',
                  style: TextStyle(color: Colors.white70, fontSize: 11.5),
                ),
                const SizedBox(height: 3),
                Text(
                  attempt.success ? 'Berhasil' : 'Gagal',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 21,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  loginActivityDateTimeLabel(attempt.time),
                  style: const TextStyle(color: Colors.white70, fontSize: 11.5),
                ),
              ],
            ),
          ),
          Container(
            width: 12,
            height: 12,
            decoration: BoxDecoration(
              color: color,
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white, width: 2),
            ),
          ),
        ],
      ),
    );
  }
}

class _UnknownAccountNotice extends StatelessWidget {
  const _UnknownAccountNotice();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF5D8),
      borderRadius: BorderRadius.circular(11),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.person_search_rounded, color: Color(0xFFB57900)),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Username tidak terhubung dengan akun NUSA. Ini dapat terjadi karena salah ketik atau percobaan menggunakan akun yang tidak terdaftar.',
            style: TextStyle(
              color: NusaColors.textPrimary,
              fontSize: 11.5,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.icon,
    required this.title,
    required this.child,
  });

  final IconData icon;
  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: NusaColors.primary, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        child,
      ],
    ),
  );
}

class _InformationRow extends StatelessWidget {
  const _InformationRow({
    required this.label,
    required this.value,
    this.leading,
    this.trailing,
  });

  final String label;
  final String value;
  final Widget? leading;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.center,
    children: [
      SizedBox(
        width: 92,
        child: Text(
          label,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 12),
        ),
      ),
      const SizedBox(width: 8),
      if (leading != null) ...[leading!, const SizedBox(width: 6)],
      Expanded(
        child: Text(
          value,
          style: const TextStyle(
            color: NusaColors.textPrimary,
            fontSize: 12.5,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      ?trailing,
    ],
  );
}

class _CopyButton extends StatelessWidget {
  const _CopyButton({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => IconButton(
    tooltip: 'Salin $label',
    onPressed: () => _copy(context, label, value),
    icon: const Icon(Icons.copy_rounded, size: 19),
  );
}

class _DetailError extends StatelessWidget {
  const _DetailError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.security_rounded,
            size: 50,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

Future<void> _copy(BuildContext context, String label, String value) async {
  await Clipboard.setData(ClipboardData(text: value));
  if (!context.mounted) return;
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text('$label disalin.')));
}

String _fullDateTimeLabel(DateTime? value) {
  if (value == null) return '-';
  final local = value.toLocal();
  const months = [
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
  ];
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  final second = local.second.toString().padLeft(2, '0');
  return '${local.day} ${months[local.month - 1]} ${local.year}, '
      '$hour.$minute.$second WIB';
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Detail aktivitas login belum dapat dimuat. Silakan coba lagi.';
