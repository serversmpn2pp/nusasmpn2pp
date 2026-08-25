import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart';

class LoginActivitySummaryStrip extends StatelessWidget {
  const LoginActivitySummaryStrip({required this.summary, super.key});

  final LoginActivitySummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.16),
          blurRadius: 15,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        _SummaryValue(value: summary.accounts, label: 'Seluruh\nAkun'),
        _SummaryValue(value: summary.loginsToday, label: 'Login\nHari Ini'),
        _SummaryValue(value: summary.neverLoggedIn, label: 'Belum\nLogin'),
        _SummaryValue(value: summary.failuresToday, label: 'Gagal\nHari Ini'),
      ],
    ),
  );
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({required this.value, required this.label});

  final int value;
  final String label;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 9,
            height: 1.15,
          ),
        ),
      ],
    ),
  );
}

class LoginUserCard extends StatelessWidget {
  const LoginUserCard({required this.user, required this.onTap, super.key});

  final LoginActivityUser user;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    elevation: 0,
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(16),
      side: const BorderSide(color: NusaColors.outline),
    ),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      key: Key('login-activity-user-${user.id}'),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                LoginActivityAvatar(initials: user.initials),
                const SizedBox(width: 11),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        user.name,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          height: 1.2,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        '${user.username} • ${user.accountType.label}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 11.5,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        user.roleLabel,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: NusaColors.textSecondary,
                          fontSize: 10.5,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 8),
                _AccountStatusBadge(active: user.active),
              ],
            ),
            const SizedBox(height: 11),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
              decoration: BoxDecoration(
                color: NusaColors.surfaceBlue.withValues(alpha: 0.7),
                borderRadius: BorderRadius.circular(11),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: _SmallInfo(
                      icon: Icons.schedule_rounded,
                      label: 'Login terakhir',
                      value: user.lastLoginAt == null
                          ? 'Belum pernah'
                          : loginActivityDateTimeLabel(user.lastLoginAt),
                    ),
                  ),
                  Container(width: 1, height: 32, color: NusaColors.outline),
                  const SizedBox(width: 10),
                  _CountBadge(
                    value: user.successCount,
                    icon: Icons.check_circle_rounded,
                    color: NusaColors.success,
                  ),
                  const SizedBox(width: 8),
                  _CountBadge(
                    value: user.failureCount,
                    icon: Icons.cancel_rounded,
                    color: const Color(0xFFB42318),
                  ),
                  const SizedBox(width: 2),
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.textSecondary,
                  ),
                ],
              ),
            ),
            if (user.lastDevice?.trim().isNotEmpty == true) ...[
              const SizedBox(height: 8),
              Row(
                children: [
                  const Icon(
                    Icons.devices_rounded,
                    size: 15,
                    color: NusaColors.textSecondary,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      user.lastDevice!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10.5,
                      ),
                    ),
                  ),
                  const Text(
                    'Lihat riwayat',
                    style: TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    ),
  );
}

class LoginAttemptCard extends StatelessWidget {
  const LoginAttemptCard({
    required this.attempt,
    required this.onTap,
    super.key,
  });

  final LoginAttempt attempt;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = attempt.success
        ? NusaColors.success
        : const Color(0xFFB42318);
    return Card(
      margin: EdgeInsets.zero,
      elevation: 0,
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: NusaColors.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        key: Key('login-attempt-${attempt.id}'),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  attempt.success ? Icons.login_rounded : Icons.gpp_bad_rounded,
                  color: color,
                  size: 23,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            attempt.displayName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              color: NusaColors.textPrimary,
                              fontSize: 13.5,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        LoginResultBadge(success: attempt.success),
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(
                      attempt.username,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 11.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 10,
                      runSpacing: 5,
                      children: [
                        _MetaLabel(
                          icon: Icons.schedule_rounded,
                          label: loginActivityDateTimeLabel(attempt.time),
                        ),
                        _MetaLabel(
                          icon: deviceIcon(attempt.device.code),
                          label: attempt.device.label,
                        ),
                        _MetaLabel(
                          icon: Icons.language_rounded,
                          label: attempt.ipAddress ?? '-',
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 3),
              const Icon(
                Icons.chevron_right_rounded,
                color: NusaColors.textSecondary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class LoginActivityAvatar extends StatelessWidget {
  const LoginActivityAvatar({
    required this.initials,
    this.size = 48,
    super.key,
  });

  final String initials;
  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    alignment: Alignment.center,
    decoration: const BoxDecoration(
      color: NusaColors.surfaceBlue,
      shape: BoxShape.circle,
    ),
    child: Text(
      initials,
      style: TextStyle(
        color: NusaColors.primary,
        fontSize: size * 0.29,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class LoginResultBadge extends StatelessWidget {
  const LoginResultBadge({required this.success, super.key});

  final bool success;

  @override
  Widget build(BuildContext context) {
    final color = success ? NusaColors.success : const Color(0xFFB42318);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        success ? 'Berhasil' : 'Gagal',
        style: TextStyle(
          color: color,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _AccountStatusBadge extends StatelessWidget {
  const _AccountStatusBadge({required this.active});

  final bool active;

  @override
  Widget build(BuildContext context) {
    final color = active ? NusaColors.success : const Color(0xFFB42318);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        active ? 'Aktif' : 'Nonaktif',
        style: TextStyle(
          color: color,
          fontSize: 9.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _SmallInfo extends StatelessWidget {
  const _SmallInfo({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Icon(icon, size: 17, color: NusaColors.primary),
      const SizedBox(width: 6),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 9,
              ),
            ),
            Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: NusaColors.textPrimary,
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    ],
  );
}

class _CountBadge extends StatelessWidget {
  const _CountBadge({
    required this.value,
    required this.icon,
    required this.color,
  });

  final int value;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 15, color: color),
      const SizedBox(width: 3),
      Text(
        '$value',
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _MetaLabel extends StatelessWidget {
  const _MetaLabel({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: NusaColors.textSecondary),
      const SizedBox(width: 4),
      Text(
        label,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

IconData deviceIcon(String code) => switch (code) {
  'android' => Icons.android_rounded,
  'ios' => Icons.phone_iphone_rounded,
  'windows' => Icons.desktop_windows_rounded,
  'mac' => Icons.laptop_mac_rounded,
  'linux' => Icons.computer_rounded,
  _ => Icons.devices_other_rounded,
};

String loginActivityDateTimeLabel(DateTime? value) {
  if (value == null) return '-';
  final local = value.toLocal();
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '${local.day} ${months[local.month - 1]} ${local.year}, $hour.$minute';
}
