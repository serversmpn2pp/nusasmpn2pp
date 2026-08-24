import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/shared/widgets/nusa_logo.dart';

class NusaBottomNavigation extends StatelessWidget {
  const NusaBottomNavigation({
    required this.selectedIndex,
    required this.onSelected,
    this.unreadCount = 0,
    super.key,
  });

  final int selectedIndex;
  final ValueChanged<int> onSelected;
  final int unreadCount;

  @override
  Widget build(BuildContext context) {
    const items = [
      (Icons.home_outlined, Icons.home_rounded, 'Beranda'),
      (Icons.receipt_long_outlined, Icons.receipt_long_rounded, 'Aktivitas'),
      (Icons.apps_outlined, Icons.apps_rounded, 'NUSA'),
      (
        Icons.notifications_none_rounded,
        Icons.notifications_rounded,
        'Notifikasi',
      ),
      (Icons.person_outline_rounded, Icons.person_rounded, 'Profil'),
    ];

    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(top: BorderSide(color: NusaColors.outline)),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.08),
            blurRadius: 20,
            offset: const Offset(0, -6),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 76,
          child: Row(
            children: List.generate(items.length, (index) {
              final (icon, selectedIcon, label) = items[index];

              if (index == 2) {
                return Expanded(
                  child: _CenterNavigationItem(
                    key: const Key('bottom-nav-2'),
                    selected: selectedIndex == index,
                    onTap: () => onSelected(index),
                  ),
                );
              }

              return Expanded(
                child: _NavigationItem(
                  key: Key('bottom-nav-$index'),
                  icon: selectedIndex == index ? selectedIcon : icon,
                  label: label,
                  selected: selectedIndex == index,
                  showDot: index == 3 && unreadCount > 0,
                  onTap: () => onSelected(index),
                ),
              );
            }),
          ),
        ),
      ),
    );
  }
}

class _NavigationItem extends StatelessWidget {
  const _NavigationItem({
    required this.icon,
    required this.label,
    required this.selected,
    required this.showDot,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final bool showDot;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? NusaColors.primary : NusaColors.textSecondary;

    return InkWell(
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              Icon(icon, color: color, size: 24),
              if (showDot)
                const Positioned(
                  right: -2,
                  top: -1,
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      color: NusaColors.accent,
                      shape: BoxShape.circle,
                    ),
                    child: SizedBox.square(dimension: 7),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 3),
          FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              label,
              maxLines: 1,
              style: TextStyle(
                color: color,
                fontSize: 10,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _CenterNavigationItem extends StatelessWidget {
  const _CenterNavigationItem({
    required this.selected,
    required this.onTap,
    super.key,
  });

  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      customBorder: const CircleBorder(),
      child: Transform.translate(
        offset: const Offset(0, -13),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 180),
              width: 58,
              height: 58,
              padding: const EdgeInsets.all(11),
              decoration: BoxDecoration(
                color: NusaColors.primary,
                shape: BoxShape.circle,
                border: Border.all(
                  color: NusaColors.accent,
                  width: selected ? 3 : 2,
                ),
                boxShadow: [
                  BoxShadow(
                    color: NusaColors.primary.withValues(alpha: 0.28),
                    blurRadius: 14,
                    offset: const Offset(0, 7),
                  ),
                ],
              ),
              child: const NusaLogo(size: 36),
            ),
            const SizedBox(height: 2),
            const Text(
              'NUSA',
              style: TextStyle(
                color: NusaColors.primary,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
