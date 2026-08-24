import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';
import 'package:nusa/features/menu/presentation/widgets/menu_cards.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class MenuGroupView extends ConsumerWidget {
  const MenuGroupView({required this.groupCode, super.key});

  final String groupCode;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final catalog = ref.watch(menuControllerProvider);
    final groupTitle = catalog.value?.groupByCode(groupCode)?.label;

    return Scaffold(
      appBar: AppBar(title: Text(groupTitle ?? 'Menu NUSA')),
      body: catalog.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (error, stackTrace) => _MenuGroupError(
          error: error,
          onRetry: ref.read(menuControllerProvider.notifier).refresh,
        ),
        data: (catalog) {
          final group = catalog.groupByCode(groupCode);
          if (group == null) {
            return const _MenuGroupNotFound();
          }

          return _MenuGroupContent(
            group: group,
            onRefresh: ref.read(menuControllerProvider.notifier).refresh,
          );
        },
      ),
    );
  }
}

class _MenuGroupContent extends StatelessWidget {
  const _MenuGroupContent({required this.group, required this.onRefresh});

  final MenuGroup group;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final groupedItems = <String, List<MenuEntry>>{};
    for (final item in group.items) {
      groupedItems
          .putIfAbsent(item.subgroup ?? 'Menu Utama', () => [])
          .add(item);
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        key: PageStorageKey<String>('menu-group-scroll-${group.code}'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: [
          _MenuGroupHeader(group: group),
          const SizedBox(height: 22),
          for (final section in groupedItems.entries) ...[
            NusaSectionTitle(title: section.key),
            const SizedBox(height: 10),
            _SubmenuGrid(
              items: section.value,
              color: nusaMenuGroupColor(group.code),
              onOpen: (item) => _openMenu(context, item),
            ),
            const SizedBox(height: 22),
          ],
        ],
      ),
    );
  }

  void _openMenu(BuildContext context, MenuEntry item) {
    if (item.isAvailable) {
      context.push(item.route!);
      return;
    }

    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(
            '${item.label} belum tersedia di aplikasi mobile. '
            'Modul ini akan dibangun pada tahap berikutnya.',
          ),
        ),
      );
  }
}

class _MenuGroupHeader extends StatelessWidget {
  const _MenuGroupHeader({required this.group});

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
                  '${group.items.length} sub-menu · $availableCount siap dibuka',
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

class _SubmenuGrid extends StatelessWidget {
  const _SubmenuGrid({
    required this.items,
    required this.color,
    required this.onOpen,
  });

  final List<MenuEntry> items;
  final Color color;
  final ValueChanged<MenuEntry> onOpen;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth < 310 ? 2 : 3;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: columns == 2 ? 0.98 : 0.72,
          ),
          itemCount: items.length,
          itemBuilder: (context, index) {
            final item = items[index];
            return NusaMenuEntryCard(
              item: item,
              color: color,
              onTap: () => onOpen(item),
            );
          },
        );
      },
    );
  }
}

class _MenuGroupError extends StatelessWidget {
  const _MenuGroupError({required this.error, required this.onRetry});

  final Object error;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final message = error is AppException
        ? (error as AppException).message
        : 'Menu belum dapat dimuat.';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
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
}

class _MenuGroupNotFound extends StatelessWidget {
  const _MenuGroupNotFound();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Padding(
        padding: EdgeInsets.all(24),
        child: Text('Kelompok menu tidak ditemukan.'),
      ),
    );
  }
}
