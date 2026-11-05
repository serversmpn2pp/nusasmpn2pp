import 'dart:convert';

import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

class QuranVerse {
  const QuranVerse(this.number, this.text);
  final int number;
  final String text;
}

class QuranSurah {
  const QuranSurah({
    required this.number,
    required this.name,
    required this.arabicName,
    required this.opening,
    required this.verses,
  });
  final int number;
  final String name, arabicName, opening;
  final List<QuranVerse> verses;

  // The source stores the unnumbered opening before verse 1. Layout separates
  // the opening without changing any of the original Arabic characters.
  String verseText(QuranVerse verse) =>
      verse.number == 1 && verse.text.startsWith('$opening ')
      ? verse.text.substring(opening.length + 1)
      : verse.text;
}

class QuranCatalog {
  const QuranCatalog(this.surahs, this.copyrightNotice);
  final List<QuranSurah> surahs;
  final String copyrightNotice;
}

class QuranReadingRepository {
  QuranReadingRepository({AssetBundle? bundle})
    : _bundle = bundle ?? rootBundle;
  final AssetBundle _bundle;
  Future<QuranCatalog>? _catalog;
  QuranCatalog? _loaded;

  Future<QuranCatalog> load() async {
    if (_loaded != null) return _loaded!;
    try {
      return _loaded = await (_catalog ??= _load());
    } catch (_) {
      _catalog = null;
      rethrow;
    }
  }

  Future<QuranCatalog> _load() async {
    final data = jsonDecode(
      await _bundle.loadString('assets/quran/juz30.json'),
    ) as Map<String, dynamic>;
    final surahs = <QuranSurah>[];
    for (final value in data['surahs'] as List<dynamic>) {
      final row = Map<String, dynamic>.from(value as Map);
      final verses = [
        for (final verse in row['verses'] as List<dynamic>)
          QuranVerse(verse['number'] as int, verse['text'] as String),
      ];
      final number = row['number'] as int;
      if (number != 78 + surahs.length ||
          verses.length != row['verse_count'] ||
          verses.isEmpty) {
        throw const FormatException('Daftar ayat tidak lengkap.');
      }
      for (var i = 0; i < verses.length; i++) {
        if (verses[i].number != i + 1 || verses[i].text.isEmpty) {
          throw const FormatException('Urutan ayat tidak valid.');
        }
      }
      surahs.add(
        QuranSurah(
          number: number,
          name: row['name'] as String,
          arabicName: row['arabic_name'] as String,
          opening: row['opening'] as String,
          verses: List.unmodifiable(verses),
        ),
      );
    }
    if (surahs.length != 37 ||
        surahs.fold<int>(0, (sum, s) => sum + s.verses.length) != 564) {
      throw const FormatException('Teks Juz 30 tidak lengkap.');
    }
    return QuranCatalog(
      List.unmodifiable(surahs),
      (data['source'] as Map)['copyright_notice'] as String,
    );
  }
}

final quranReadingRepositoryProvider = Provider(
  (_) => QuranReadingRepository(),
);
