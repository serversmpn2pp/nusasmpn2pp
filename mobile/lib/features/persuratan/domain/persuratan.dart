import 'dart:typed_data';

import 'package:nusa/features/auth/domain/pengguna.dart';

typedef SuratData = Map<String, dynamic>;

SuratData suratMap(Object? v) => v is Map ? Map<String, dynamic>.from(v) : {};
List<SuratData> suratItems(Object? v) =>
    v is List ? v.whereType<Map>().map(suratMap).toList() : [];
int suratInt(Object? v) => int.tryParse('$v') ?? 0;
String suratText(Object? v, [String fallback = '-']) =>
    v == null || '$v'.trim().isEmpty ? fallback : '$v';
String suratDate(Object? v) {
  final d = DateTime.tryParse('$v')?.toLocal();
  if (d == null) return '-';
  return '${d.day.toString().padLeft(2, '0')}/${d.month.toString().padLeft(2, '0')}/${d.year}'
      '${'$v'.contains('T') ? ' ${d.hour.toString().padLeft(2, '0')}:${d.minute.toString().padLeft(2, '0')}' : ''}';
}

bool suratStaff(Pengguna? u) =>
    u != null &&
    (u.jenisAkun == 'Pegawai' ||
        (u.administrator && u.jenisAkun == 'Administrator sistem'));
bool suratPermission(Pengguna? u, String p) =>
    suratStaff(u) && (u!.administrator || u.izin.contains(p));

enum SuratModule {
  archive('persuratan', 'Arsip Surat'),
  approval('persuratan/pengesahan', 'Pengesahan Surat'),
  myLetters('surat-saya', 'Surat & Penugasan Saya'),
  monitoring('persuratan/tindak-lanjut', 'Monitoring Tindak Lanjut Surat'),
  myTasks('tindak-lanjut-surat', 'Tindak Lanjut Surat Saya');

  const SuratModule(this.path, this.title);
  final String path;
  final String title;
  String get route => '/$path';
  bool get tasks => this == monitoring || this == myTasks;
  String detailRoute(int id) => tasks
      ? '/tindak-lanjut-surat/$id'
      : this == myLetters
      ? '/surat-saya/$id'
      : '/persuratan/$id';
  bool canOpen(Pengguna? u) {
    if (!suratStaff(u)) return false;
    if (this == myLetters || this == myTasks) return u!.jenisAkun == 'Pegawai';
    if (this == approval) return suratPermission(u, 'surat.sahkan');
    return [
      'surat.lihat',
      'surat.kelola',
      'surat.sahkan',
      'surat.disposisi',
    ].any((p) => suratPermission(u, p));
  }
}

class SuratPage {
  SuratPage(this.data);
  final SuratData data;
  List<SuratData> get items => suratItems(data['items']);
  int get page => suratInt(suratMap(data['paginasi'])['halaman']);
  int get total => suratInt(suratMap(data['paginasi'])['total']);
  bool get hasNext =>
      suratMap(data['paginasi'])['ada_halaman_berikutnya'] == true;
}

class SuratUpload {
  SuratUpload({required this.name, required this.bytes, this.image = false});
  final String name;
  final Uint8List bytes;
  final bool image;
  static const maxBytes = 10 * 1024 * 1024;
}

enum SuratFileSource { camera, gallery, file }
