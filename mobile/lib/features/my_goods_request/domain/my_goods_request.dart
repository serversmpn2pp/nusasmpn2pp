import 'package:nusa/features/goods_request/domain/goods_request.dart';

class MyGoodsRequestPage {
  const MyGoodsRequestPage({
    required this.items,
    required this.summary,
    required this.pagination,
    required this.statuses,
    required this.status,
  });
  factory MyGoodsRequestPage.fromJson(Map<String, dynamic> json) {
    final options = _map(json['pilihan']);
    final filter = _map(json['filter']);
    return MyGoodsRequestPage(
      items: _list(json['items'], GoodsRequest.fromJson),
      summary: MyGoodsRequestSummary.fromJson(_map(json['ringkasan'])),
      pagination: GoodsRequestPagination.fromJson(_map(json['paginasi'])),
      statuses: _list(options['status'], GoodsRequestOption.fromJson),
      status: filter['status'] as String? ?? 'semua',
    );
  }
  final List<GoodsRequest> items;
  final MyGoodsRequestSummary summary;
  final GoodsRequestPagination pagination;
  final List<GoodsRequestOption> statuses;
  final String status;
  MyGoodsRequestPage append(MyGoodsRequestPage next) => MyGoodsRequestPage(
    items: [...items, ...next.items],
    summary: next.summary,
    pagination: next.pagination,
    statuses: next.statuses,
    status: next.status,
  );
}

class MyGoodsRequestDetail {
  const MyGoodsRequestDetail({required this.request, required this.canCancel});
  factory MyGoodsRequestDetail.fromJson(Map<String, dynamic> json) =>
      MyGoodsRequestDetail(
        request: GoodsRequest.fromJson(_map(json['pengajuan'])),
        canCancel:
            _map(json['hak_akses'])['dapat_membatalkan'] as bool? ?? false,
      );
  final GoodsRequest request;
  final bool canCancel;
}

class MyGoodsCatalogPage {
  const MyGoodsCatalogPage({
    required this.items,
    required this.pagination,
    required this.query,
  });
  factory MyGoodsCatalogPage.fromJson(Map<String, dynamic> json) =>
      MyGoodsCatalogPage(
        items: _list(json['items'], MyGoodsCatalogItem.fromJson),
        pagination: GoodsRequestPagination.fromJson(_map(json['paginasi'])),
        query: _map(json['filter'])['kata_kunci'] as String? ?? '',
      );
  final List<MyGoodsCatalogItem> items;
  final GoodsRequestPagination pagination;
  final String query;
  MyGoodsCatalogPage append(MyGoodsCatalogPage next) => MyGoodsCatalogPage(
    items: [...items, ...next.items],
    pagination: next.pagination,
    query: next.query,
  );
}

class MyGoodsCatalogItem {
  const MyGoodsCatalogItem({
    required this.id,
    required this.code,
    required this.name,
    required this.category,
    required this.goodsType,
    required this.goodsTypeLabel,
    required this.managementType,
    required this.serviceType,
    required this.serviceLabel,
    required this.availableQuantity,
    required this.unit,
    required this.available,
  });
  factory MyGoodsCatalogItem.fromJson(Map<String, dynamic> json) =>
      MyGoodsCatalogItem(
        id: _int(json['id']),
        code: json['kode'] as String? ?? '-',
        name: json['nama'] as String? ?? '-',
        category: json['kategori'] as String? ?? '-',
        goodsType: json['jenis_barang'] as String? ?? '-',
        goodsTypeLabel: json['jenis_barang_label'] as String? ?? '-',
        managementType: json['tipe_pengelolaan'] as String? ?? '-',
        serviceType: json['jenis_layanan'] as String? ?? '-',
        serviceLabel: json['jenis_layanan_label'] as String? ?? '-',
        availableQuantity: _double(json['jumlah_tersedia']),
        unit: json['satuan'] as String? ?? 'unit',
        available: json['tersedia'] as bool? ?? false,
      );
  final int id;
  final String code;
  final String name;
  final String category;
  final String goodsType;
  final String goodsTypeLabel;
  final String managementType;
  final String serviceType;
  final String serviceLabel;
  final double availableQuantity;
  final String unit;
  final bool available;
  bool get mustReturn => serviceType == 'peminjaman';
}

class MyGoodsRequestSummary {
  const MyGoodsRequestSummary({
    required this.total,
    required this.pending,
    required this.fulfilled,
    required this.finished,
  });
  factory MyGoodsRequestSummary.fromJson(Map<String, dynamic> json) =>
      MyGoodsRequestSummary(
        total: _int(json['semua']),
        pending: _int(json['menunggu']),
        fulfilled: _int(json['dipenuhi']),
        finished: _int(json['selesai']),
      );
  final int total;
  final int pending;
  final int fulfilled;
  final int finished;
}

class MyGoodsRequestFormValue {
  const MyGoodsRequestFormValue({
    required this.goodsId,
    required this.quantity,
    required this.requiredDate,
    required this.purpose,
    this.plannedReturn,
  });
  final int goodsId;
  final double quantity;
  final DateTime requiredDate;
  final DateTime? plannedReturn;
  final String purpose;
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<T> _list<T>(dynamic value, T Function(Map<String, dynamic>) convert) =>
    value is List
    ? value
          .whereType<Map>()
          .map((item) => convert(Map<String, dynamic>.from(item)))
          .toList()
    : <T>[];
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _double(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
