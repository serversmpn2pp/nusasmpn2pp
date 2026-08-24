class Pengguna {
  const Pengguna({
    required this.id,
    required this.nama,
    required this.username,
    required this.jenisAkun,
    required this.administrator,
    required this.wajibGantiKataSandi,
    required this.peran,
    required this.izin,
    this.terakhirLoginPada,
  });

  factory Pengguna.fromJson(Map<String, dynamic> json) {
    return Pengguna(
      id: json['id'] as int,
      nama: json['nama'] as String,
      username: json['username'] as String,
      jenisAkun: json['jenis_akun'] as String,
      administrator: json['administrator'] as bool? ?? false,
      wajibGantiKataSandi: json['wajib_ganti_kata_sandi'] as bool? ?? false,
      peran: _stringList(json['peran']),
      izin: _stringList(json['izin']),
      terakhirLoginPada: DateTime.tryParse(
        json['terakhir_login_pada'] as String? ?? '',
      ),
    );
  }

  final int id;
  final String nama;
  final String username;
  final String jenisAkun;
  final bool administrator;
  final bool wajibGantiKataSandi;
  final List<String> peran;
  final List<String> izin;
  final DateTime? terakhirLoginPada;

  static List<String> _stringList(Object? value) {
    return switch (value) {
      List() => List.unmodifiable(value.map((item) => item.toString())),
      _ => const [],
    };
  }
}
