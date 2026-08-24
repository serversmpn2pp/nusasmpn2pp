import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';
import 'package:nusa/features/menu/presentation/widgets/menu_cards.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class MenuView extends StatefulWidget {
  const MenuView({
    required this.catalog,
    required this.onRefresh,
    required this.onOpenGroup,
    required this.onOpen,
    super.key,
  });

  final AsyncValue<MenuCatalog> catalog;
  final Future<void> Function() onRefresh;
  final ValueChanged<MenuGroup> onOpenGroup;
  final ValueChanged<MenuEntry> onOpen;

  @override
  State<MenuView> createState() => _MenuViewState();
}

class _MenuViewState extends State<MenuView> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return widget.catalog.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (error, stackTrace) =>
          _MenuErrorState(error: error, onRetry: widget.onRefresh),
      data: (catalog) {
        final searchResults = _query.isEmpty
            ? const <_MenuSearchResult>[]
            : _filteredEntries(catalog.groups);

        return RefreshIndicator(
          onRefresh: widget.onRefresh,
          child: ListView(
            key: const PageStorageKey<String>('admin-menu-scroll'),
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
            children: [
              _CatalogHeader(itemCount: catalog.itemCount),
              const SizedBox(height: 16),
              TextField(
                key: const Key('menu-search'),
                controller: _searchController,
                onChanged: (value) => setState(() => _query = value.trim()),
                decoration: InputDecoration(
                  hintText: 'Cari sub-menu NUSA',
                  prefixIcon: const Icon(Icons.search_rounded),
                  suffixIcon: _query.isEmpty
                      ? null
                      : IconButton(
                          onPressed: () {
                            FocusScope.of(context).unfocus();
                            _searchController.clear();
                            setState(() => _query = '');
                          },
                          icon: const Icon(Icons.close_rounded),
                          tooltip: 'Hapus pencarian',
                        ),
                ),
              ),
              const SizedBox(height: 18),
              if (_query.isEmpty) ...[
                const NusaSectionTitle(title: 'Kelompok Menu'),
                const SizedBox(height: 10),
                _MenuGroupGrid(
                  groups: catalog.groups,
                  onOpen: widget.onOpenGroup,
                ),
              ] else if (searchResults.isEmpty)
                const _EmptySearchState()
              else ...[
                NusaSectionTitle(
                  title: 'Hasil Pencarian (${searchResults.length})',
                ),
                const SizedBox(height: 10),
                _SearchResultGrid(results: searchResults, onOpen: _openMenu),
              ],
            ],
          ),
        );
      },
    );
  }

  List<_MenuSearchResult> _filteredEntries(List<MenuGroup> groups) {
    final normalizedQuery = _query.toLowerCase();
    final results = <_MenuSearchResult>[];

    for (final group in groups) {
      final groupMatches = '${group.label} ${group.description}'
          .toLowerCase()
          .contains(normalizedQuery);
      for (final item in group.items) {
        if (groupMatches || item.matches(_query)) {
          results.add(_MenuSearchResult(group: group, item: item));
        }
      }
    }

    return results;
  }

  void _openMenu(MenuEntry item) {
    if (item.isAvailable) {
      widget.onOpen(item);
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

class _MenuSearchResult {
  const _MenuSearchResult({required this.group, required this.item});

  final MenuGroup group;
  final MenuEntry item;
}

class _MenuGroupGrid extends StatelessWidget {
  const _MenuGroupGrid({required this.groups, required this.onOpen});

  final List<MenuGroup> groups;
  final ValueChanged<MenuGroup> onOpen;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth < 300 ? 1 : 2;

        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: columns == 1 ? 2.25 : 1.14,
          ),
          itemCount: groups.length,
          itemBuilder: (context, index) {
            final group = groups[index];
            return NusaMenuGroupCard(group: group, onTap: () => onOpen(group));
          },
        );
      },
    );
  }
}

class _SearchResultGrid extends StatelessWidget {
  const _SearchResultGrid({required this.results, required this.onOpen});

  final List<_MenuSearchResult> results;
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
          itemCount: results.length,
          itemBuilder: (context, index) {
            final result = results[index];
            return NusaMenuEntryCard(
              item: result.item,
              color: nusaMenuGroupColor(result.group.code),
              onTap: () => onOpen(result.item),
            );
          },
        );
      },
    );
  }
}

class _CatalogHeader extends StatelessWidget {
  const _CatalogHeader({required this.itemCount});

  final int itemCount;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryLight],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.grid_view_rounded,
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
                  'Menu Administrasi',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$itemCount menu sesuai hak akses akun Anda',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _EmptySearchState extends StatelessWidget {
  const _EmptySearchState();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 52),
      child: Column(
        children: [
          Icon(
            Icons.search_off_rounded,
            size: 48,
            color: Theme.of(context).colorScheme.outline,
          ),
          const SizedBox(height: 12),
          Text(
            'Menu tidak ditemukan',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          const Text('Coba gunakan kata kunci yang lebih singkat.'),
        ],
      ),
    );
  }
}

class _MenuErrorState extends StatelessWidget {
  const _MenuErrorState({required this.error, required this.onRetry});

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
