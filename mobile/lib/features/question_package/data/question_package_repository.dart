import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/question_package/data/question_package_remote_data_source.dart';
import 'package:nusa/features/question_package/domain/question_package.dart';

class QuestionPackageRepository {
  const QuestionPackageRepository(this._remote);
  final QuestionPackageRemoteDataSource _remote;

  Future<QuestionPackagePage> fetch({
    required String query,
    required int? eventId,
    required String status,
    required int page,
  }) =>
      _remote.fetch(query: query, eventId: eventId, status: status, page: page);
  Future<QuestionPackageDetail> detail(int id) => _remote.detail(id);
  Future<QuestionPackageDetail> save(int id, QuestionPackagePayload payload) =>
      _remote.save(id, payload);
}

final questionPackageRepositoryProvider = Provider<QuestionPackageRepository>(
  (ref) => QuestionPackageRepository(
    ref.watch(questionPackageRemoteDataSourceProvider),
  ),
);
