import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/domain/menu_catalog.dart';
import 'package:nusa/features/menu/presentation/widgets/menu_cards.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:nusa/shared/widgets/nusa_section_title.dart';

class HumasHubView extends ConsumerStatefulWidget {
  const HumasHubView({super.key});
  @override
  ConsumerState<HumasHubView> createState() => _HumasHubViewState();
}

class _HumasHubViewState extends ConsumerState<HumasHubView> {
  HumasData? _data;
  HumasData _references = {};
  Object? _error;
  bool _loading = true;
  String? _year;
  String _period = 'tahunan';
  int _request = 0;
  @override
  void initState() {
    super.initState();
    Future.microtask(_load);
  }

  Future<void> _load() async {
    if (!mounted) return;
    final request = ++_request;
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (!humasCanViewDashboard(user)) {
      setState(() {
        _data = null;
        _references = {};
        _error = null;
        _loading = false;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final repository = ref.read(humasRepositoryProvider);
      final result = await Future.wait([
        repository.get('dashboard', {
          if (_year != null) 'tahun_pelajaran_id': _year,
          'periode': _period,
        }),
        repository.get('dashboard/referensi'),
      ]);
      if (!mounted || request != _request) return;
      setState(() {
        _data = result[0];
        _references = result[1];
        _loading = false;
      });
    } catch (error) {
      if (mounted && request == _request) {
        setState(() {
          _error = error;
          _loading = false;
        });
      }
    }
  }

  Future<void> _refresh() async {
    await Future.wait([
      ref.read(menuControllerProvider.notifier).refresh(),
      _load(),
    ]);
  }

  Future<void> _openMenu(MenuEntry item) async {
    final module = HumasModule.fromMenuCode(item.code);
    final user = ref.read(authControllerProvider).value?.session?.pengguna;
    if (module == null || !module.canOpen(user) || !item.isAvailable) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Menu ini belum tersedia untuk akun Anda. Muat ulang menu atau hubungi admin.',
          ),
        ),
      );
      return;
    }
    await context.push(module.route);
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final user = ref.watch(authControllerProvider).value?.session?.pengguna;
    final canViewDashboard = humasCanViewDashboard(user);
    final menu = ref.watch(menuControllerProvider);
    final group = menu.value?.groupByCode('humas');
    final items =
        group?.items.where((item) {
          final module = HumasModule.fromMenuCode(item.code);
          return module != null && module.canOpen(user);
        }).toList() ??
        [];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Humas'),
        actions: [
          IconButton(
            tooltip: 'Muat ulang Humas',
            onPressed: _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            children: [
              HumasCard(
                title: 'Hubungan Sekolah & Masyarakat',
                subtitle: 'Agenda, komunikasi, dan layanan sekolah dalam satu tempat.',
                trailing: const Icon(
                  Icons.forum_rounded,
                  color: NusaColors.primary,
                ),
              ),
              if (menu.isLoading) const LinearProgressIndicator(),
              if (menu.hasError) HumasErrorView(menu.error!, onRetry: _refresh),
              if (items.isNotEmpty) ...[
                const NusaSectionTitle(title: 'Menu Humas'),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) {
                    final columns = constraints.maxWidth < 340 ? 2 : 3;
                    final textScale = MediaQuery.textScalerOf(context).scale(1);
                    return GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: columns,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        mainAxisExtent: 132 * textScale.clamp(1.0, 2.0),
                      ),
                      itemCount: items.length,
                      itemBuilder: (context, index) => NusaMenuEntryCard(
                        item: items[index],
                        color: NusaColors.primary,
                        onTap: () => _openMenu(items[index]),
                      ),
                    );
                  },
                ),
                const SizedBox(height: 24),
              ],
              if (canViewDashboard) ...[
                const NusaSectionTitle(title: 'Ringkasan Humas'),
                const SizedBox(height: 12),
              ],
              if (_references.isNotEmpty) ...[
                NusaDropdownField<String>(
                  fieldKey: const Key('humas-dashboard-year'),
                  value: _year ?? '',
                  options: [
                    const NusaDropdownOption(value: '', label: 'Tahun aktif'),
                    for (final year in humasItems(_references['tahun']))
                      NusaDropdownOption(
                        value: '${year['id']}',
                        label: humasText(year['nama']),
                      ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Tahun pelajaran',
                  ),
                  onChanged: (v) {
                    _year = v == '' ? null : v;
                    _load();
                  },
                ),
                const SizedBox(height: 12),
                NusaDropdownField<String>(
                  fieldKey: const Key('humas-dashboard-period'),
                  value: _period,
                  options: [
                    for (final entry in humasMap(
                      _references['periode'],
                    ).entries.where((e) => e.key != 'kustom'))
                      NusaDropdownOption(
                        value: entry.key,
                        label: entry.value.toString(),
                      ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Periode ringkasan',
                  ),
                  onChanged: (v) {
                    _period = v!;
                    _load();
                  },
                ),
                const SizedBox(height: 16),
              ],
              if (_loading)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.all(24),
                    child: CircularProgressIndicator(),
                  ),
                ),
              if (_error != null) HumasErrorView(_error!, onRetry: _load),
              if (canViewDashboard &&
                  _data != null &&
                  !_loading &&
                  _error == null) ...[
                Text(
                  humasText(_data!['label_periode']),
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 12),
                for (final metric in humasMap(_data!['metrik']).values)
                  HumasCard(
                    title: humasText(humasMap(metric)['label']),
                    trailing: Text(
                      '${humasInt(humasMap(metric)['jumlah'])}',
                      style: const TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: NusaColors.primary,
                      ),
                    ),
                    subtitle: humasText(humasMap(metric)['dasar'], ''),
                  ),
                for (final attention in humasItems(_data!['perhatian']))
                  HumasCard(
                    title: humasText(attention['label']),
                    subtitle: humasText(
                      attention['pesan'],
                      humasText(attention['jumlah'], ''),
                    ),
                    trailing: const Icon(Icons.info_outline_rounded),
                  ),
                const Text(
                  'Agenda Mendatang',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 12),
                for (final agenda in humasItems(_data!['agenda']))
                  HumasCard(
                    title: humasText(agenda['judul']),
                    subtitle:
                        '${humasDate(agenda['waktu_mulai'])}\n${humasText(agenda['tempat'])}',
                    onTap: () async {
                      await context.push('/humas/agenda/${agenda['id']}');
                      if (mounted) await _load();
                    },
                  ),
                if (humasItems(_data!['agenda']).isEmpty)
                  const Text('Tidak ada agenda mendatang pada periode ini.'),
              ],
              if (!canViewDashboard &&
                  items.isEmpty &&
                  !menu.isLoading &&
                  !menu.hasError)
                const Text('Belum ada menu Humas untuk akun ini.'),
            ],
          ),
        ),
      ),
    );
  }
}
