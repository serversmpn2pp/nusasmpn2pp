import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/humas/data/humas_repository.dart';
import 'package:nusa/features/humas/domain/humas.dart';
import 'package:nusa/features/humas/presentation/widgets/humas_widgets.dart';
import 'package:nusa/features/menu/application/menu_controller.dart';
import 'package:nusa/features/menu/presentation/menu_visuals.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

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
    if (user == null ||
        humasParentUser(user) ||
        humasStudentUser(user) ||
        !(user.administrator || user.izin.contains('dashboard_humas.lihat'))) {
      if (mounted) setState(() => _loading = false);
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

  @override
  Widget build(BuildContext context) {
    final menu = ref.watch(menuControllerProvider);
    final group = menu.value?.groupByCode('humas');
    final items =
        group?.items.where((m) => m.code != 'dashboard-humas').toList() ?? [];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Humas'),
        actions: [
          IconButton(
            onPressed: () {
              ref.read(menuControllerProvider.notifier).refresh();
              _load();
            },
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _load,
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
              if (menu.hasError)
                HumasErrorView(
                  menu.error!,
                  onRetry: () =>
                      ref.read(menuControllerProvider.notifier).refresh(),
                ),
              LayoutBuilder(
                builder: (context, constraints) => Wrap(
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    for (final item in items)
                      SizedBox(
                        width: (constraints.maxWidth - 12) / 2,
                        child: Card(
                          clipBehavior: Clip.antiAlias,
                          child: InkWell(
                            onTap: () => context.push(item.route!),
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                children: [
                                  Icon(
                                    nusaMenuEntryIcon(item),
                                    size: 30,
                                    color: NusaColors.primary,
                                  ),
                                  const SizedBox(height: 12),
                                  Text(
                                    item.label,
                                    textAlign: TextAlign.center,
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(height: 24),
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
              if (_data != null && !_loading) ...[
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
                    onTap: () => context.push('/humas/agenda/${agenda['id']}'),
                  ),
                if (humasItems(_data!['agenda']).isEmpty)
                  const Text('Tidak ada agenda mendatang pada periode ini.'),
              ],
              if (items.isEmpty && !menu.isLoading && !menu.hasError)
                const Text('Belum ada menu Humas untuk akun ini.'),
            ],
          ),
        ),
      ),
    );
  }
}
