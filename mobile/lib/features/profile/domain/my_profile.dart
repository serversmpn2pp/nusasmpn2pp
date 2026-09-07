import 'dart:typed_data';

import 'package:nusa/features/auth/domain/pengguna.dart';

enum MyProfileKind { account, employee, student, parent }

class MyProfile {
  const MyProfile({
    required this.kind,
    required this.name,
    required this.username,
    required this.canChangePhoto,
    required this.details,
    required this.children,
    this.photoUrl,
    this.lastLoginAt,
    this.school,
  });

  factory MyProfile.fromJson(Map<String, dynamic> json) {
    return MyProfile(
      kind: switch (json['jenis']) {
        'pegawai' => MyProfileKind.employee,
        'siswa' => MyProfileKind.student,
        'orang_tua' => MyProfileKind.parent,
        _ => MyProfileKind.account,
      },
      name: json['nama'] as String? ?? '-',
      username: json['username'] as String? ?? '-',
      photoUrl: json['foto_url'] as String?,
      canChangePhoto: json['dapat_ubah_foto'] as bool? ?? false,
      lastLoginAt: DateTime.tryParse(
        json['terakhir_login_pada'] as String? ?? '',
      ),
      details: MyProfileDetails.fromJson(_map(json['data'])),
      school: json['sekolah'] is Map
          ? MyProfileSchool.fromJson(_map(json['sekolah']))
          : null,
      children: _list(json['anak'], MyProfileChild.fromJson),
    );
  }

  final MyProfileKind kind;
  final String name;
  final String username;
  final String? photoUrl;
  final bool canChangePhoto;
  final DateTime? lastLoginAt;
  final MyProfileDetails details;
  final MyProfileSchool? school;
  final List<MyProfileChild> children;
}

class MyProfileDetails {
  const MyProfileDetails({
    this.fullName,
    this.nip,
    this.nuptk,
    this.nik,
    this.gender,
    this.birthPlace,
    this.birthDate,
    this.address,
    this.email,
    this.phone,
    this.employeeType,
    this.position,
    this.lastEducation,
    this.educationMajor,
    this.graduationYear,
    this.notes,
    this.nis,
    this.nisn,
    this.religion,
    this.whatsAppNumber,
  });

  factory MyProfileDetails.fromJson(Map<String, dynamic> json) {
    return MyProfileDetails(
      fullName: _text(json['nama_lengkap']),
      nip: _text(json['nip']),
      nuptk: _text(json['nuptk']),
      nik: _text(json['nik']),
      gender: _text(json['jenis_kelamin']),
      birthPlace: _text(json['tempat_lahir']),
      birthDate: _text(json['tanggal_lahir']),
      address: _text(json['alamat']),
      email: _text(json['email']),
      phone: _text(json['no_hp']),
      employeeType: _text(json['jenis_pegawai']),
      position: _text(json['jabatan_utama']),
      lastEducation: _text(json['pendidikan_terakhir']),
      educationMajor: _text(json['jurusan_pendidikan']),
      graduationYear: _integer(json['tahun_lulus']),
      notes: _text(json['keterangan']),
      nis: _text(json['nis']),
      nisn: _text(json['nisn']),
      religion: _text(json['agama']),
      whatsAppNumber: _text(json['nomor_wa']),
    );
  }

  final String? fullName;
  final String? nip;
  final String? nuptk;
  final String? nik;
  final String? gender;
  final String? birthPlace;
  final String? birthDate;
  final String? address;
  final String? email;
  final String? phone;
  final String? employeeType;
  final String? position;
  final String? lastEducation;
  final String? educationMajor;
  final int? graduationYear;
  final String? notes;
  final String? nis;
  final String? nisn;
  final String? religion;
  final String? whatsAppNumber;
}

class MyProfileSchool {
  const MyProfileSchool({
    this.className,
    this.attendanceNumber,
    this.academicYear,
    this.homeroomTeacher,
  });

  factory MyProfileSchool.fromJson(Map<String, dynamic> json) {
    return MyProfileSchool(
      className: _text(json['kelas']),
      attendanceNumber: _integer(json['nomor_absen']),
      academicYear: _text(json['tahun_pelajaran']),
      homeroomTeacher: _text(json['wali_kelas']),
    );
  }

  final String? className;
  final int? attendanceNumber;
  final String? academicYear;
  final String? homeroomTeacher;
}

class MyProfileChild {
  const MyProfileChild({
    required this.id,
    required this.name,
    required this.primary,
    this.nisn,
    this.relationship,
    this.photoUrl,
    this.school,
  });

  factory MyProfileChild.fromJson(Map<String, dynamic> json) {
    return MyProfileChild(
      id: _integer(json['id']) ?? 0,
      name: json['nama'] as String? ?? '-',
      nisn: _text(json['nisn']),
      relationship: _text(json['hubungan']),
      primary: json['utama'] as bool? ?? false,
      photoUrl: _text(json['foto_url']),
      school: json['sekolah'] is Map
          ? MyProfileSchool.fromJson(_map(json['sekolah']))
          : null,
    );
  }

  final int id;
  final String name;
  final String? nisn;
  final String? relationship;
  final bool primary;
  final String? photoUrl;
  final MyProfileSchool? school;
}

class MyProfileUpdateResult {
  const MyProfileUpdateResult({
    required this.message,
    required this.profile,
    required this.user,
  });

  final String message;
  final MyProfile profile;
  final Pengguna user;
}

enum MyProfilePhotoSource { camera, gallery }

class MyProfilePhotoFile {
  const MyProfilePhotoFile({required this.name, required this.bytes});

  final String name;
  final Uint8List bytes;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

List<T> _list<T>(Object? value, T Function(Map<String, dynamic>) factory) =>
    (value as List<dynamic>? ?? const [])
        .whereType<Map>()
        .map((item) => factory(Map<String, dynamic>.from(item)))
        .toList(growable: false);

String? _text(Object? value) {
  final text = value?.toString().trim();
  return text == null || text.isEmpty ? null : text;
}

int? _integer(Object? value) => value is num ? value.toInt() : null;
