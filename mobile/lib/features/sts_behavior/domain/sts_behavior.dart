class StsOption {
  const StsOption(this.id, this.label);
  factory StsOption.fromJson(Map<String, dynamic> json) =>
      StsOption(_int(json['id']), json['label'] as String);
  final int id;
  final String label;
}

class StsBehaviorPage {
  StsBehaviorPage.fromJson(Map<String, dynamic> json)
    : activityId = _nullableInt((json['filter'] as Map)['kegiatan_id']),
      classId = _nullableInt((json['filter'] as Map)['kelas_id']),
      activities = _options((json['opsi'] as Map)['kegiatan']),
      classes = _options((json['opsi'] as Map)['kelas']),
      contentOptions = {
        for (final item in (json['opsi'] as Map)['isi'] as List)
          item['kode'] as String: item['label'] as String,
      },
      report = json['konteks'] == null
          ? null
          : StsBehaviorReport.fromJson(json['konteks'] as Map<String, dynamic>);
  final int? activityId;
  final int? classId;
  final List<StsOption> activities;
  final List<StsOption> classes;
  final Map<String, String> contentOptions;
  final StsBehaviorReport? report;
}

class StsBehaviorReport {
  StsBehaviorReport.fromJson(Map<String, dynamic> json)
    : start = json['awal'] as String,
      end = json['akhir'] as String,
      reportDate = json['tanggal_rapor'] as String,
      version = _int(json['versi_rapor']),
      contentVersion = _int(json['versi_isi_perilaku']),
      content = json['isi'] as String,
      emptyMessage = json['pesan_kosong'] as String,
      notices = (json['pesan'] as List).cast<String>(),
      canChangeContent = json['dapat_simpan_isi'] == true,
      canReview = json['dapat_periksa'] == true,
      counselors = _options(json['guru_bk']),
      deputies = _options(json['wakil_kesiswaan']),
      students = [
        for (final item in json['siswa'] as List)
          StsBehaviorStudent.fromJson(item as Map<String, dynamic>),
      ];
  final String start, end, reportDate, content, emptyMessage;
  final int version, contentVersion;
  final bool canChangeContent, canReview;
  final List<String> notices;
  final List<StsOption> counselors, deputies;
  final List<StsBehaviorStudent> students;

  Map<String, dynamic> contentPayload(String choice) => {
    'isi_lampiran_perilaku': choice,
    'versi_isi_perilaku': contentVersion,
    'versi_rapor': version,
  };
}

class StsBehaviorStudent {
  StsBehaviorStudent.fromJson(Map<String, dynamic> json)
    : memberId = _int(json['id']),
      name = json['nama'] as String,
      attendanceNumber = _nullableInt(json['nomor_absen']),
      ready = json['siap'] == true,
      version = _int(json['versi']),
      fingerprint = json['sidik_sumber'] as String,
      note = json['catatan'] as String? ?? '',
      counselorId = _nullableInt(json['guru_bk_id']),
      deputyId = _nullableInt(json['wakil_kesiswaan_id']),
      incidentCount = _int((json['ringkasan'] as Map)['jumlah_kejadian']),
      pointsIn = _int((json['ringkasan'] as Map)['poin_masuk']),
      pointsOut = _int((json['ringkasan'] as Map)['poin_dikurangi']),
      balance = _int((json['ringkasan'] as Map)['saldo']),
      rows = [
        for (final item in json['baris'] as List)
          StsBehaviorRow.fromJson(item as Map<String, dynamic>),
      ];
  // This is anggota_kelas.id, NOT siswa.id. Do not substitute student IDs.
  final int memberId;
  final String name, fingerprint, note;
  final int? attendanceNumber, counselorId, deputyId;
  final int version, incidentCount, pointsIn, pointsOut, balance;
  final bool ready;
  final List<StsBehaviorRow> rows;

  Map<String, dynamic> reviewPayload({
    required Map<String, Map<String, String>> editedRows,
    required String note,
  }) => {
    'versi': version,
    'sidik_sumber': fingerprint,
    'baris': editedRows,
    'catatan': note.trim(),
  };
}

class StsBehaviorRow {
  StsBehaviorRow.fromJson(Map<String, dynamic> json)
    : key = json['kunci'] as String,
      date = json['tanggal'] as String,
      incident = json['kejadian'] as String,
      action = json['tindakan'] as String,
      points = _nullableInt(json['poin']),
      status = json['status'] as String;
  final String key, date, incident, action, status;
  final int? points;
}

int _int(Object? value) => value is int ? value : int.parse('$value');
int? _nullableInt(Object? value) => value == null ? null : _int(value);
List<StsOption> _options(Object? value) => [
  for (final item in value as List)
    StsOption.fromJson(item as Map<String, dynamic>),
];
