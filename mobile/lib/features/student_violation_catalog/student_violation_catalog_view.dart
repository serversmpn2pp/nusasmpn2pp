import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/network/dio_provider.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

final studentViolationCatalogProvider = FutureProvider.autoDispose
    .family<Map<String, dynamic>, (String, int?, int)>((ref, filter) async {
      final response = await ref
          .watch(dioProvider)
          .get<Map<String, dynamic>>(
            'aturan-pelanggaran-poin',
            queryParameters: {
              'cari': filter.$1,
              if (filter.$2 != null) 'kategori_id': filter.$2,
              'halaman': filter.$3,
            },
          );
      return response.data!['data'] as Map<String, dynamic>;
    });

class StudentViolationCatalogView extends ConsumerStatefulWidget {
  const StudentViolationCatalogView({super.key});
  @override
  ConsumerState<StudentViolationCatalogView> createState() =>
      _StudentViolationCatalogViewState();
}

class _StudentViolationCatalogViewState
    extends ConsumerState<StudentViolationCatalogView> {
  final _search = TextEditingController();
  Timer? _debounce;
  String _query = '';
  int? _category;
  int _page = 1;
  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filter = (_query, _category, _page);
    final state = ref.watch(studentViolationCatalogProvider(filter));
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Aturan Pelanggaran & Poin'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () =>
                ref.invalidate(studentViolationCatalogProvider(filter)),
          ),
        ],
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
            child: TextField(
              key: const Key('student-violation-search'),
              controller: _search,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.search_rounded),
                hintText: 'Cari nama atau kode pelanggaran',
              ),
              onChanged: (value) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 350), () {
                  if (mounted) {
                    setState(() {
                      _query = value.trim();
                      _page = 1;
                    });
                  }
                });
              },
            ),
          ),
          Expanded(
            child: state.when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (error, _) => Center(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        error is DioException &&
                                error.response?.statusCode == 403
                            ? 'Daftar aturan ini tersedia untuk akun siswa.'
                            : 'Aturan belum dapat dimuat. Periksa koneksi lalu coba kembali.',
                      ),
                      TextButton(
                        onPressed: () => ref.invalidate(
                          studentViolationCatalogProvider(filter),
                        ),
                        child: const Text('Coba lagi'),
                      ),
                    ],
                  ),
                ),
              ),
              data: (data) {
                final items = (data['items'] as List)
                    .cast<Map<String, dynamic>>();
                final categories = (data['kategori'] as List)
                    .cast<Map<String, dynamic>>();
                return RefreshIndicator(
                  onRefresh: () async {
                    ref.invalidate(studentViolationCatalogProvider(filter));
                    await ref.read(
                      studentViolationCatalogProvider(filter).future,
                    );
                  },
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(16, 2, 16, 28),
                    children: [
                      NusaDropdownField<int>(
                        fieldKey: const Key('student-violation-category'),
                        value: _category ?? 0,
                        decoration: const InputDecoration(
                          labelText: 'Kategori',
                          prefixIcon: Icon(Icons.filter_alt_outlined),
                        ),
                        options: [
                          const NusaDropdownOption(
                            value: 0,
                            label: 'Semua kategori',
                          ),
                          for (final item in categories)
                            NusaDropdownOption(
                              value: item['id'] as int,
                              label: item['nama'] as String,
                            ),
                        ],
                        onChanged: (value) => setState(() {
                          _category = value == 0 ? null : value;
                          _page = 1;
                        }),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        '${data['total']} aturan aktif',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          color: NusaColors.primary,
                        ),
                      ),
                      const SizedBox(height: 5),
                      const Text(
                        'Panduan aturan sekolah. Daftar ini berbeda dari riwayat pelanggaran atau saldo poin pribadi.',
                        style: TextStyle(
                          fontSize: 12,
                          color: NusaColors.textSecondary,
                        ),
                      ),
                      const SizedBox(height: 12),
                      if (items.isEmpty)
                        const Padding(
                          padding: EdgeInsets.all(24),
                          child: Text(
                            'Tidak ada aturan yang sesuai pencarian.',
                          ),
                        ),
                      for (final item in items)
                        Card(
                          margin: const EdgeInsets.only(bottom: 10),
                          child: Padding(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    const Icon(
                                      Icons.rule_rounded,
                                      color: NusaColors.primary,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        item['nama'] as String,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Text(
                                      '${item['poin']} poin',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w800,
                                        color: NusaColors.primary,
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 8),
                                Text('${item['kode']} · ${item['kategori']}'),
                                const SizedBox(height: 5),
                                Text(
                                  'Tingkat ${item['tingkat']}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: NusaColors.textSecondary,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      if ((data['halaman_terakhir'] as int) > 1)
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            TextButton(
                              onPressed: _page > 1
                                  ? () => setState(() => _page--)
                                  : null,
                              child: const Text('Sebelumnya'),
                            ),
                            Text('$_page / ${data['halaman_terakhir']}'),
                            TextButton(
                              onPressed:
                                  _page < (data['halaman_terakhir'] as int)
                                  ? () => setState(() => _page++)
                                  : null,
                              child: const Text('Berikutnya'),
                            ),
                          ],
                        ),
                    ],
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
