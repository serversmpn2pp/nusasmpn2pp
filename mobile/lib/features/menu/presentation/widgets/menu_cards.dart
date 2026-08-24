import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';

class NusaMenuGroupCard extends StatelessWidget {
  const NusaMenuGroupCard({
    required this.group,
    required this.onTap,
    super.key,
  });

  final MenuGroup group;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = nusaMenuGroupColor(group.code);

    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(18),
        side: const BorderSide(color: NusaColors.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        key: Key('menu-group-${group.code}'),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.11),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      nusaMenuGroupIcon(group.icon),
                      color: color,
                      size: 25,
                    ),
                  ),
                  const Spacer(),
                  const Icon(
                    Icons.arrow_forward_rounded,
                    size: 19,
                    color: NusaColors.textSecondary,
                  ),
                ],
              ),
              const Spacer(),
              Text(
                group.label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  height: 1.15,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                '${group.items.length} sub-menu',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class NusaMenuEntryCard extends StatelessWidget {
  const NusaMenuEntryCard({
    required this.item,
    required this.color,
    required this.onTap,
    super.key,
  });

  final MenuEntry item;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: NusaColors.outline),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        key: Key('menu-item-${item.code}'),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(8, 10, 8, 8),
          child: Column(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.11),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(nusaMenuEntryIcon(item), color: color, size: 24),
              ),
              const SizedBox(height: 8),
              Expanded(
                child: Center(
                  child: Text(
                    item.label,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      height: 1.18,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 4),
              _AvailabilityLabel(isAvailable: item.isAvailable),
            ],
          ),
        ),
      ),
    );
  }
}

class _AvailabilityLabel extends StatelessWidget {
  const _AvailabilityLabel({required this.isAvailable});

  final bool isAvailable;

  @override
  Widget build(BuildContext context) {
    final color = isAvailable ? NusaColors.success : NusaColors.textSecondary;

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 5,
          height: 5,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 4),
        Flexible(
          child: Text(
            isAvailable ? 'Buka' : 'Segera',
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: color,
              fontSize: 9,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}
