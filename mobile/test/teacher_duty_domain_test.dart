import 'package:flutter_test/flutter_test.dart';
import 'package:nusa/features/teacher_duty/domain/teacher_duty.dart';

void main() {
  test('parses teacher duty schedule catalog', () {
    final catalog = DutyScheduleCatalog.fromJson({
      'items': [
        {
          'id': 11,
          'tahun_pelajaran': {'id': 2, 'nama': '2026/2027', 'aktif': true},
          'pegawai': {'id': 8, 'nama': 'Guru NUSA', 'nip': '1980'},
          'hari': 'kamis',
          'hari_label': 'Kamis',
          'aktif': true,
          'keterangan': 'Tim pagi',
        },
      ],
      'ringkasan': {'jadwal_aktif': 4, 'jumlah_guru': 3, 'hari_terisi': 2},
      'tahun_pelajaran': [
        {'id': 2, 'nama': '2026/2027', 'aktif': true},
      ],
      'hari': [
        {'kode': 'kamis', 'label': 'Kamis'},
      ],
      'filter': {
        'tahun_pelajaran_id': 2,
        'hari': 'semua',
        'status': 'aktif',
        'cari': '',
      },
      'hak_akses': {'dapat_kelola': true},
    });

    expect(catalog.items.single.teacher.name, 'Guru NUSA');
    expect(catalog.summary.activeSchedules, 4);
    expect(catalog.academicYearId, 2);
    expect(catalog.canManage, isTrue);
  });

  test('parses my duty dashboard and attendance eligibility', () {
    final dashboard = MyDutyDashboard.fromJson({
      'tanggal_label': 'Kamis, 13 Agustus 2026',
      'tahun_pelajaran': {'id': 2, 'nama': '2026/2027'},
      'hari_ini': {'kode': 'kamis', 'label': 'Kamis'},
      'jadwal_saya': [
        {'hari': 'kamis', 'hari_label': 'Kamis'},
      ],
      'guru_mapel_aktif': true,
      'dapat_mencatat_hari_ini': true,
      'ringkasan': {
        'total': 32,
        'hadir': 25,
        'sakit': 2,
        'izin': 1,
        'belum_scan': 4,
      },
      'items': [
        {
          'anggota_kelas_id': 7,
          'siswa': {'nama': 'Siswa NUSA', 'inisial': 'SN', 'nis': '1007'},
          'kelas': {'nama': 'VII.A'},
          'presensi': {
            'status': 'belum_scan',
            'status_label': 'Belum scan',
            'dapat_dicatat': true,
          },
        },
      ],
      'kelas': [
        {'id': 3, 'nama': 'VII.A'},
      ],
      'filter': {'status': 'semua'},
      'paginasi': {'halaman': 1, 'ada_halaman_berikutnya': false},
    });

    expect(dashboard.canRecordToday, isTrue);
    expect(dashboard.mySchedules.single.code, 'kamis');
    expect(dashboard.items.single.canRecord, isTrue);
    expect(dashboard.summary.notScanned, 4);
  });
}
