import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';
import 'package:nusa/features/menu/presentation/widgets/menu_cards.dart';

class NusaMenuGroupHeader extends StatelessWidget {
  const NusaMenuGroupHeader({required this.group, super.key});

  final MenuGroup group;

  @override
  Widget build(BuildContext context) {
    final color = nusaMenuGroupColor(group.code);
    final availableCount = group.items.where((item) => item.isAvailable).length;

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [NusaColors.primary, color],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.13),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(17),
            ),
            child: Icon(
              nusaMenuGroupIcon(group.icon),
              color: Colors.white,
              size: 30,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Menu ${group.label}',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  group.description,
                  style: const TextStyle(
                    color: Colors.white70,
                    fontSize: 12,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
                Text(
                  '${group.items.length} sub-menu · $availableCount tersedia',
                  style: const TextStyle(
                    color: NusaColors.accent,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class NusaSubmenuGrid extends StatelessWidget {
  const NusaSubmenuGrid({
    required this.items,
    required this.color,
    required this.onOpen,
    super.key,
  });

  final List<MenuEntry> items;
  final Color color;
  final ValueChanged<MenuEntry> onOpen;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final scale = MediaQuery.textScalerOf(context).scale(1).clamp(1.0, 2.0);
        final columns = constraints.maxWidth < 280 || scale > 1.3 ? 2 : 3;
        final extent = items.any((item) => !item.isAvailable) ? 140.0 : 116.0;
        const spacing = 10.0;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: spacing,
            crossAxisSpacing: spacing,
            mainAxisExtent: extent * scale,
          ),
          itemCount: items.length,
          itemBuilder: (context, index) {
            final item = items[index];
            return NusaMenuEntryCard(
              item: item,
              color: color,
              unavailableLabel: item.status == 'web' ? 'Web' : 'Segera hadir',
              onTap: () => onOpen(item),
            );
          },
        );
      },
    );
  }
}
