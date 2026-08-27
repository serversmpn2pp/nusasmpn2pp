import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/survey_statement/data/survey_statement_remote_data_source.dart';
import 'package:nusa/features/survey_statement/domain/survey_statement.dart';

final class SurveyStatementRepository {
  SurveyStatementRepository(this._remote);

  final SurveyStatementRemoteDataSource _remote;

  Future<SurveyStatementPage> fetch({
    required String query,
    required String status,
    required int page,
  }) => _remote.fetch(query: query, status: status, page: page);

  Future<void> create(SurveyStatementFormValue value) => _remote.create(value);

  Future<void> update({
    required int id,
    required SurveyStatementFormValue value,
  }) => _remote.update(id: id, value: value);

  Future<void> updateStatus({required int id, required bool active}) =>
      _remote.updateStatus(id: id, active: active);
}

final surveyStatementRepositoryProvider = Provider<SurveyStatementRepository>(
  (ref) => SurveyStatementRepository(
    ref.watch(surveyStatementRemoteDataSourceProvider),
  ),
);
