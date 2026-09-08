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
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.11),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(nusaMenuEntryIcon(item), color: color, size: 25),
              ),
              const SizedBox(height: 9),
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
              if (!item.isAvailable) ...[
                const SizedBox(height: 5),
                const _ComingSoonLabel(),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ComingSoonLabel extends StatelessWidget {
  const _ComingSoonLabel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      decoration: BoxDecoration(
        color: NusaColors.textSecondary.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(20),
      ),
      child: const Text(
        'Segera hadir',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: NusaColors.textSecondary,
          fontSize: 8.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
