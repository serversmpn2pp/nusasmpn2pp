import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/lesson_period/data/lesson_period_remote_data_source.dart';
import 'package:nusa/features/lesson_period/domain/lesson_period.dart';

final class LessonPeriodRepository {
  LessonPeriodRepository(this._remote);

  final LessonPeriodRemoteDataSource _remote;

  Future<LessonPeriodCatalog> fetch({
    required String day,
    required String status,
  }) => _remote.fetch(day: day, status: status);

  Future<void> create({
    required List<String> days,
    required String insertionPosition,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) => _remote.create(
    days: days,
    insertionPosition: insertionPosition,
    label: label,
    startTime: startTime,
    endTime: endTime,
    type: type,
    active: active,
    notes: notes,
  );

  Future<void> update({
    required int id,
    required String? label,
    required String startTime,
    required String endTime,
    required String type,
    required bool active,
    String? notes,
  }) => _remote.update(
    id: id,
    label: label,
    startTime: startTime,
    endTime: endTime,
    type: type,
    active: active,
    notes: notes,
  );
}

final lessonPeriodRepositoryProvider = Provider<LessonPeriodRepository>(
  (ref) =>
      LessonPeriodRepository(ref.watch(lessonPeriodRemoteDataSourceProvider)),
);
