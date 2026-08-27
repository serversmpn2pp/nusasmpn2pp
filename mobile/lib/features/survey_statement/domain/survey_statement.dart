class SurveyStatementPage {
  const SurveyStatementPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.query,
    required this.status,
    required this.nextOrder,
  });

  factory SurveyStatementPage.fromJson(Map<String, dynamic> json) {
    final filter = _map(json['filter']);
    return SurveyStatementPage(
      items: (json['items'] as List<dynamic>? ?? const [])
          .whereType<Map>()
          .map(
            (item) => SurveyStatement.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(growable: false),
      summary: SurveyStatementSummary.fromJson(_map(json['ringkasan'])),
      pagination: SurveyStatementPagination.fromJson(_map(json['paginasi'])),
      query: filter['cari'] as String? ?? '',
      status: filter['status'] as String? ?? 'semua',
      nextOrder: _integer(json['urutan_berikutnya']),
    );
  }

  final List<SurveyStatement> items;
  final SurveyStatementSummary summary;
  final SurveyStatementPagination pagination;
  final String query;
  final String status;
  final int nextOrder;

  SurveyStatementPage append(SurveyStatementPage next) => SurveyStatementPage(
    items: [...items, ...next.items],
    summary: next.summary,
    pagination: next.pagination,
    query: next.query,
    status: next.status,
    nextOrder: next.nextOrder,
  );
}

class SurveyStatement {
  const SurveyStatement({
    required this.id,
    required this.code,
    required this.statement,
    required this.order,
    required this.active,
  });

  factory SurveyStatement.fromJson(Map<String, dynamic> json) =>
      SurveyStatement(
        id: _integer(json['id']),
        code: json['kode'] as String? ?? '-',
        statement: json['pernyataan'] as String? ?? '-',
        order: _integer(json['urutan']),
        active: json['aktif'] as bool? ?? false,
      );

  final int id;
  final String code;
  final String statement;
  final int order;
  final bool active;
}

class SurveyStatementSummary {
  const SurveyStatementSummary({
    required this.total,
    required this.active,
    required this.inactive,
  });

  factory SurveyStatementSummary.fromJson(Map<String, dynamic> json) =>
      SurveyStatementSummary(
        total: _integer(json['total']),
        active: _integer(json['aktif']),
        inactive: _integer(json['nonaktif']),
      );

  final int total;
  final int active;
  final int inactive;
}

class SurveyStatementPagination {
  const SurveyStatementPagination({
    required this.page,
    required this.total,
    required this.hasNextPage,
  });

  factory SurveyStatementPagination.fromJson(Map<String, dynamic> json) =>
      SurveyStatementPagination(
        page: _integer(json['halaman']),
        total: _integer(json['total']),
        hasNextPage: json['ada_halaman_berikutnya'] as bool? ?? false,
      );

  final int page;
  final int total;
  final bool hasNextPage;
}

class SurveyStatementFormValue {
  const SurveyStatementFormValue({
    required this.statement,
    required this.order,
    required this.active,
  });

  final String statement;
  final int order;
  final bool active;
}

Map<String, dynamic> _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : const {};

int _integer(Object? value) => value is num ? value.toInt() : 0;
