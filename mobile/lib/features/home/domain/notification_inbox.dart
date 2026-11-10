import 'package:nusa/features/home/domain/home_dashboard.dart';

class NotificationInbox {
  const NotificationInbox({
    required this.items,
    required this.unreadCount,
    this.nextBeforeId,
    this.isLoadingMore = false,
  });

  factory NotificationInbox.fromJson(Map<String, dynamic> json) {
    final pagination = json['paginasi'] as Map<String, dynamic>;
    return NotificationInbox(
      items: List.unmodifiable(
        (json['items'] as List).map(
          (item) => AppNotification.fromJson(item as Map<String, dynamic>),
        ),
      ),
      unreadCount: (json['jumlah_belum_dibaca'] as num).toInt(),
      nextBeforeId: pagination['ada_lagi'] == true
          ? (pagination['sebelum_id_berikutnya'] as num?)?.toInt()
          : null,
    );
  }

  final List<AppNotification> items;
  final int unreadCount;
  final int? nextBeforeId;
  final bool isLoadingMore;

  NotificationInbox copyWith({
    List<AppNotification>? items,
    int? unreadCount,
    bool? isLoadingMore,
  }) => NotificationInbox(
    items: items ?? this.items,
    unreadCount: unreadCount ?? this.unreadCount,
    nextBeforeId: nextBeforeId,
    isLoadingMore: isLoadingMore ?? this.isLoadingMore,
  );

  NotificationInbox append(NotificationInbox next) => NotificationInbox(
    items: List.unmodifiable(
      {
        for (final item in [...items, ...next.items]) item.id: item,
      }.values,
    ),
    unreadCount: next.unreadCount,
    nextBeforeId: next.nextBeforeId,
  );

  NotificationInbox markRead(int? id, int count, {required bool unreadOnly}) {
    if (unreadOnly && count == 0) {
      return const NotificationInbox(items: [], unreadCount: 0);
    }
    final updated = items.map((item) {
      if ((id != null && item.id != id) || !item.unread) return item;
      return AppNotification(
        id: item.id,
        type: item.type,
        typeLabel: item.typeLabel,
        title: item.title,
        message: item.message,
        unread: false,
        createdAt: item.createdAt,
        relativeTime: item.relativeTime,
        mobileDestination: item.mobileDestination,
      );
    });
    return copyWith(
      unreadCount: count,
      items: List.unmodifiable(
        unreadOnly ? updated.where((item) => item.unread) : updated,
      ),
    );
  }
}
