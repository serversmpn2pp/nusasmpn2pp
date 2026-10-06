import 'dart:math';

import 'package:nusa/features/auth/domain/pengguna.dart';

typedef HumasData = Map<String, dynamic>;

bool humasParentUser(Pengguna? user) => user?.jenisAkun == 'Orang tua';
bool humasStudentUser(Pengguna? user) => user?.jenisAkun == 'Siswa';

bool humasCanViewDashboard(Pengguna? user) =>
    user != null &&
    !humasParentUser(user) &&
    !humasStudentUser(user) &&
    (user.administrator || user.izin.contains('dashboard_humas.lihat'));

HumasData humasMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<HumasData> humasItems(Object? value) => value is List
    ? value.whereType<Map>().map(humasMap).toList()
    : <HumasData>[];
String humasText(Object? value, [String fallback = '—']) =>
    value == null || value.toString().trim().isEmpty
    ? fallback
    : value.toString();
int humasInt(Object? value) => int.tryParse('$value') ?? 0;
String humasDate(Object? value) {
  final date = DateTime.tryParse('$value')?.toLocal();
  if (date == null) return '—';
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}'
      '${value.toString().contains('T') ? ' • ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}' : ''}';
}

// One UUID belongs to one submission and is retained on network/validation errors.
String humasUuid() {
  final random = Random.secure();
  final bytes = List<int>.generate(16, (_) => random.nextInt(256));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
}

enum HumasModule {
  agenda('agenda', 'Agenda & Pertemuan', 'agenda_humas'),
  documents('dokumen', 'Pusat Dokumen Humas', 'dokumen_humas'),
  complaints('pengaduan', 'Aspirasi & Pengaduan', 'pengaduan_humas'),
  feedback('umpan-balik', 'Rekap Umpan Balik', 'umpan_balik_humas'),
  invitations('pertemuan-saya', 'Undangan Pertemuan Saya', ''),
  myComplaints('pengaduan-saya', 'Aspirasi & Pengaduan Saya', ''),
  myFeedback('umpan-balik-saya', 'Umpan Balik Saya', '');

  const HumasModule(this.path, this.title, this.permission);
  final String path;
  final String title;
  final String permission;
  String get route => '/humas/$path';
  String get menuCode => switch (this) {
    agenda => 'agenda-humas',
    documents => 'dokumen-humas',
    complaints => 'pengaduan-humas',
    feedback => 'umpan-balik-humas',
    invitations => 'pertemuan-saya',
    myComplaints => 'pengaduan-saya',
    myFeedback => 'umpan-balik-saya',
  };

  static HumasModule? fromMenuCode(String code) {
    for (final module in values) {
      if (module.menuCode == code) return module;
    }
    return null;
  }

  bool get parentOnly => permission.isEmpty;
  bool canOpen(Pengguna? user) {
    if (user == null) return false;
    if (parentOnly) return humasParentUser(user);
    if (humasParentUser(user) || humasStudentUser(user)) return false;
    return user.administrator ||
        user.izin.any(
          (p) =>
              p == '$permission.lihat' ||
              p == '$permission.kelola' ||
              (this == complaints && p == '$permission.tangani'),
        );
  }

  bool canManage(Pengguna? user) =>
      canOpen(user) &&
      !parentOnly &&
      (user!.administrator || user.izin.contains('$permission.kelola'));
}

class HumasPage {
  const HumasPage(this.items, this.page, this.hasNext, this.total, this.data);
  factory HumasPage.fromJson(HumasData data) {
    final pagination = humasMap(data['paginasi']);
    return HumasPage(
      humasItems(data['items']),
      humasInt(pagination['halaman']),
      pagination['ada_halaman_berikutnya'] == true,
      humasInt(pagination['total']),
      data,
    );
  }
  final List<HumasData> items;
  final int page;
  final bool hasNext;
  final int total;
  final HumasData data;
}
