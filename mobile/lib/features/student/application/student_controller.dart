import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/student/data/student_repository.dart';
import 'package:nusa/features/student/domain/student.dart';

class StudentListController extends AsyncNotifier<StudentPage> {
  String _query = '';
  String _status = 'semua';
  int _requestVersion = 0;

  @override
  Future<StudentPage> build() => _fetch(page: 1);

  Future<void> search(String query) async {
    _query = query.trim();
    await _reload();
  }

  Future<void> filterStatus(String status) async {
    if (_status == status) {
      return;
    }

    _status = status;
    await _reload();
  }

  Future<void> refresh() => _reload();

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.pagination.hasNextPage) {
      return;
    }

    final next = await _fetch(page: current.pagination.page + 1);
    state = AsyncData(current.append(next));
  }

  Future<void> _reload() async {
    final version = ++_requestVersion;
    state = const AsyncLoading();

    try {
      final result = await _fetch(page: 1);
      if (version == _requestVersion) {
        state = AsyncData(result);
      }
    } catch (error, stackTrace) {
      if (version == _requestVersion) {
        state = AsyncError(error, stackTrace);
      }
    }
  }

  Future<StudentPage> _fetch({required int page}) async {
    try {
      return await ref
          .read(studentRepositoryProvider)
          .fetchStudents(query: _query, status: _status, page: page);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final studentListControllerProvider =
    AsyncNotifierProvider.autoDispose<StudentListController, StudentPage>(
      StudentListController.new,
    );

final studentDetailProvider = FutureProvider.autoDispose
    .family<StudentDetail, int>((ref, id) async {
      try {
        return await ref.read(studentRepositoryProvider).fetchStudent(id);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });
