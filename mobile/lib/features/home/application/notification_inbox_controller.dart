import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/home/application/home_controller.dart';
import 'package:nusa/features/home/data/notification_inbox_remote_data_source.dart';
import 'package:nusa/features/home/domain/notification_inbox.dart';

class NotificationInboxController extends AsyncNotifier<NotificationInbox> {
  String _status = 'semua';
  int _requestVersion = 0;
  String get status => _status;

  @override
  Future<NotificationInbox> build() {
    ref.watch(
      authControllerProvider.select((auth) => auth.value?.session?.pengguna.id),
    );
    _requestVersion++;
    _status = 'semua';
    ref.onDispose(() => _requestVersion++);
    return _fetch();
  }

  Future<NotificationInbox> _fetch({int? beforeId}) => _guard(
    () => ref
        .read(notificationInboxRemoteDataSourceProvider)
        .fetch(status: _status, beforeId: beforeId),
  );

  Future<void> filter(String status) async {
    if (_status == status) return;
    _status = status;
    await refresh();
  }

  Future<void> refresh() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();
    try {
      final next = await _fetch();
      if (version == _requestVersion) state = AsyncData(next);
    } catch (error, stack) {
      if (version == _requestVersion) state = AsyncError(error, stack);
    }
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (state.isLoading ||
        current == null ||
        current.isLoadingMore ||
        current.nextBeforeId == null) {
      return;
    }
    final version = _requestVersion;
    state = AsyncData(current.copyWith(isLoadingMore: true));
    try {
      final next = await _fetch(beforeId: current.nextBeforeId);
      if (version == _requestVersion) state = AsyncData(current.append(next));
    } catch (_) {
      if (version != _requestVersion) return;
      state = AsyncData(current);
      rethrow;
    }
  }

  Future<void> markRead(int id) => _mark(id);
  Future<void> markAllRead() => _mark(null);

  Future<void> _mark(int? id) async {
    final version = _requestVersion;
    final remote = ref.read(notificationInboxRemoteDataSourceProvider);
    final count = await _guard(
      () => id == null ? remote.markAllRead() : remote.markRead(id),
    );
    if (version != _requestVersion) return;
    final current = state.value;
    if (current != null) {
      state = AsyncData(
        current.markRead(id, count, unreadOnly: _status == 'belum_dibaca'),
      );
    }
    ref.invalidate(homeControllerProvider);
  }

  Future<T> _guard<T>(Future<T> Function() action) async {
    try {
      return await action();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final notificationInboxControllerProvider =
    AsyncNotifierProvider.autoDispose<
      NotificationInboxController,
      NotificationInbox
    >(NotificationInboxController.new);
