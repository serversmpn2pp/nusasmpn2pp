import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:url_launcher/url_launcher.dart';

import '../data/quran_reading_repository.dart';
import 'tahfidz_widgets.dart';

class TahfidzSurahView extends ConsumerStatefulWidget {
  const TahfidzSurahView({super.key});
  @override
  ConsumerState<TahfidzSurahView> createState() => _TahfidzSurahViewState();
}

class _TahfidzSurahViewState extends ConsumerState<TahfidzSurahView> {
  late Future<QuranCatalog> _future;
  String _query = '';
  @override
  void initState() {
    super.initState();
    _future = ref.read(quranReadingRepositoryProvider).load();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Daftar Surah Juz 30')),
    body: FutureBuilder<QuranCatalog>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.hasError) {
          return const Center(
            child: TfNotice(
              'Teks surah belum dapat dibuka. Coba jalankan ulang aplikasi.',
            ),
          );
        }
        if (!snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        final catalog = snapshot.data!;
        final query = _query.trim().toLowerCase();
        final items = catalog.surahs.where(
          (s) => '${s.number} ${s.name.toLowerCase()} ${s.arabicName}'.contains(
            query,
          ),
        );
        return Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 900),
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
              children: [
                const TfHero(
                  'Baca & Periksa Hafalan',
                  '37 surah Juz 30 dengan teks Arab berharakat dan nomor ayat. Tersedia tanpa koneksi internet.',
                ),
                TextField(
                  key: const Key('tahfidz-surah-search'),
                  decoration: const InputDecoration(
                    labelText: 'Cari nama atau nomor surah',
                    prefixIcon: Icon(Icons.search_rounded),
                  ),
                  onChanged: (v) => setState(() => _query = v),
                ),
                const SizedBox(height: 12),
                if (items.isEmpty) const TfNotice('Surah tidak ditemukan.'),
                for (final s in items)
                  TfCard(
                    '${s.number}. ${s.name}',
                    '${s.verses.length} ayat · Buka teks Arab',
                    key: Key('tahfidz-surah-${s.number}'),
                    onTap: () => tfOpen(context, TahfidzSurahReader(surah: s)),
                    child: Text(
                      s.arabicName,
                      textDirection: TextDirection.rtl,
                      textAlign: TextAlign.right,
                      style: const TextStyle(
                        fontFamily: 'AmiriQuran',
                        fontSize: 28,
                        height: 1.8,
                        color: NusaColors.primary,
                      ),
                    ),
                  ),
                _QuranSource(catalog.copyrightNotice),
              ],
            ),
          ),
        );
      },
    ),
  );
}

Future<void> openTahfidzSurah(
  BuildContext context,
  WidgetRef ref,
  int number,
) async {
  FocusManager.instance.primaryFocus?.unfocus();
  final catalog = await ref.read(quranReadingRepositoryProvider).load();
  final surah = catalog.surahs.where((s) => s.number == number).firstOrNull;
  if (surah == null) throw const FormatException('Surah belum tersedia.');
  if (context.mounted) await tfOpen(context, TahfidzSurahReader(surah: surah));
}

class TahfidzSurahReader extends StatefulWidget {
  const TahfidzSurahReader({required this.surah, super.key});
  final QuranSurah surah;
  @override
  State<TahfidzSurahReader> createState() => _TahfidzSurahReaderState();
}

class _TahfidzSurahReaderState extends State<TahfidzSurahReader> {
  final _scroll = ScrollController();
  late final Map<int, GlobalKey> _verseKeys;
  double _fontSize = 30;
  int _verse = 1;
  @override
  void initState() {
    super.initState();
    _verseKeys = {for (final v in widget.surah.verses) v.number: GlobalKey()};
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final surah = widget.surah;
    final arabicStyle = TextStyle(
      fontFamily: 'AmiriQuran',
      fontSize: _fontSize,
      height: 2.0,
      color: NusaColors.textPrimary,
    );
    return Scaffold(
      appBar: AppBar(
        title: Text(surah.name),
        actions: [
          IconButton(
            key: const Key('tahfidz-arabic-smaller'),
            tooltip: 'Perkecil huruf Arab',
            onPressed: _fontSize > 24
                ? () => setState(() => _fontSize -= 2)
                : null,
            icon: const Icon(Icons.text_decrease_rounded),
          ),
          IconButton(
            key: const Key('tahfidz-arabic-larger'),
            tooltip: 'Perbesar huruf Arab',
            onPressed: _fontSize < 44
                ? () => setState(() => _fontSize += 2)
                : null,
            icon: const Icon(Icons.text_increase_rounded),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.small(
        elevation: 0,
        backgroundColor: NusaColors.primary,
        foregroundColor: Colors.white,
        tooltip: 'Kembali ke atas',
        onPressed: () => _scroll.animateTo(
          0,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        ),
        child: const Icon(Icons.vertical_align_top_rounded),
      ),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 900),
          child: SingleChildScrollView(
            controller: _scroll,
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 80),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TfHero(
                  '${surah.number}. ${surah.name}',
                  'Juz 30 · ${surah.verses.length} ayat · Teks Utsmani',
                ),
                Text(
                  surah.arabicName,
                  textDirection: TextDirection.rtl,
                  textAlign: TextAlign.center,
                  style: arabicStyle.copyWith(fontSize: 36),
                ),
                const SizedBox(height: 10),
                NusaDropdownField<int>(
                  fieldKey: const Key('tahfidz-jump-verse'),
                  value: _verse,
                  options: [
                    for (final v in surah.verses)
                      NusaDropdownOption(
                        value: v.number,
                        label: 'Ayat ${v.number}',
                      ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Langsung ke ayat',
                    prefixIcon: Icon(Icons.format_list_numbered_rounded),
                  ),
                  onChanged: (v) {
                    if (v == null) return;
                    setState(() => _verse = v);
                    final target = _verseKeys[v]?.currentContext;
                    if (target != null) {
                      Scrollable.ensureVisible(
                        target,
                        duration: const Duration(milliseconds: 250),
                        alignment: .05,
                      );
                    }
                  },
                ),
                Container(
                  margin: const EdgeInsets.only(top: 16, bottom: 10),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 16,
                  ),
                  child: SelectableText(
                    surah.opening,
                    textDirection: TextDirection.rtl,
                    textAlign: TextAlign.center,
                    style: arabicStyle,
                  ),
                ),
                for (final v in surah.verses)
                  Container(
                    key: _verseKeys[v.number],
                    margin: const EdgeInsets.symmetric(vertical: 6),
                    padding: const EdgeInsets.fromLTRB(18, 14, 18, 20),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: NusaColors.outline),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Align(
                          alignment: Alignment.centerLeft,
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 5,
                            ),
                            decoration: BoxDecoration(
                              color: NusaColors.surfaceBlue,
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Text(
                              'Ayat ${v.number}',
                              style: const TextStyle(
                                fontSize: 12,
                                color: NusaColors.primary,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 8),
                        SelectableText(
                          surah.verseText(v),
                          key: ValueKey('tahfidz-verse-${v.number}'),
                          textDirection: TextDirection.rtl,
                          textAlign: TextAlign.right,
                          style: arabicStyle,
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 16),
                const _QuranSource(),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _QuranSource extends StatelessWidget {
  const _QuranSource([this.copyrightNotice]);
  final String? copyrightNotice;
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.center,
    children: [
      const Text(
        'Sumber teks: Tanzil Project · Uthmani 1.1',
        textAlign: TextAlign.center,
        style: TextStyle(fontSize: 11, color: NusaColors.textSecondary),
      ),
      Wrap(
        alignment: WrapAlignment.center,
        spacing: 12,
        children: [
          TextButton(
            onPressed: () => launchUrl(
              Uri.parse('https://tanzil.net/'),
              mode: LaunchMode.externalApplication,
            ),
            child: const Text('Tanzil.net'),
          ),
          TextButton(
            onPressed: () async {
              final notice =
                  copyrightNotice ??
                  await rootBundle.loadString(
                    'assets/quran/tanzil-license.txt',
                  );
              final fontLicense = await rootBundle.loadString(
                'assets/fonts/amiri-quran-OFL.txt',
              );
              if (context.mounted) {
                showDialog<void>(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Sumber & Lisensi'),
                    content: SingleChildScrollView(
                      child: SelectableText(
                        '$notice\n\nFont: Amiri Quran\n$fontLicense',
                      ),
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(ctx),
                        child: const Text('Tutup'),
                      ),
                    ],
                  ),
                );
              }
            },
            child: const Text('Sumber & Lisensi'),
          ),
        ],
      ),
    ],
  );
}
