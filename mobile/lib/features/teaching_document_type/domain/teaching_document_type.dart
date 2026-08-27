class TeachingDocumentTypePage {
  const TeachingDocumentTypePage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.query,
    required this.status,
    required this.requirement,
    required this.nextOrder,
  });

  factory TeachingDocumentTypePage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return TeachingDocumentTypePage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                TeachingDocumentType.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      summary: TeachingDocumentTypeSummary.fromJson(_map(json['ringkasan'])),
      pagination: TeachingDocumentTypePagination.fromJson(
        _map(json['paginasi']),
      ),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      requirement: filter['kewajiban'] as String? ?? 'semua',
      nextOrder: _integer(json['urutan_berikutnya']),
    );
  }

  final List<TeachingDocumentType> items;
  final TeachingDocumentTypeSummary summary;
  final TeachingDocumentTypePagination pagination;
  final String query;
  final String status;
  final String requirement;
  final int nextOrder;

  TeachingDocumentTypePage append(TeachingDocumentTypePage next) =>
      TeachingDocumentTypePage(
        items: [...items, ...next.items],
        summary: next.summary,
        pagination: next.pagination,
        query: next.query,
        status: next.status,
        requirement: next.requirement,
        nextOrder: next.nextOrder,
      );
}

class TeachingDocumentType {
  const TeachingDocumentType({
    required this.id,
    required this.code,
    required this.name,
    required this.mandatory,
    required this.order,
    required this.active,
    required this.documentCount,
    this.description,
  });

  factory TeachingDocumentType.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentType(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        description: json['deskripsi'] as String?,
        mandatory: json['wajib'] as bool? ?? false,
        order: _integer(json['urutan']),
        active: json['aktif'] as bool? ?? false,
        documentCount: _integer(json['jumlah_dokumen']),
      );

  final int id;
  final String code;
  final String name;
  final String? description;
  final bool mandatory;
  final int order;
  final bool active;
  final int documentCount;
}

class TeachingDocumentTypeSummary {
  const TeachingDocumentTypeSummary({
    required this.total,
    required this.active,
    required this.mandatory,
  });

  factory TeachingDocumentTypeSummary.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentTypeSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        mandatory: _integer(json['wajib']),
      );

  final int total;
  final int active;
  final int mandatory;
}

class TeachingDocumentTypePagination {
  const TeachingDocumentTypePagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory TeachingDocumentTypePagination.fromJson(Map<String, dynamic> json) =>
      TeachingDocumentTypePagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class TeachingDocumentTypeFormValue {
  const TeachingDocumentTypeFormValue({
    required this.code,
    required this.name,
    required this.mandatory,
    required this.order,
    required this.active,
    this.description,
  });

  final String code;
  final String name;
  final String? description;
  final bool mandatory;
  final int order;
  final bool active;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;
