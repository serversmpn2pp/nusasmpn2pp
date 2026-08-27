import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/learning_survey/data/learning_survey_remote_data_source.dart';
import 'package:nusa/features/learning_survey/domain/learning_survey.dart';

class LearningSurveyRepository {
  LearningSurveyRepository(this._remote);

  final LearningSurveyRemoteDataSource _remote;

  Future<LearningSurveyPage> fetch(LearningSurveyArgs args) =>
      _remote.fetch(args);

  Future<LearningSurveySubmitResult> submit(
    LearningSurveyArgs args,
    LearningSurveySubmission value,
  ) => _remote.submit(args, value);
}

final learningSurveyRepositoryProvider = Provider<LearningSurveyRepository>(
  (ref) => LearningSurveyRepository(
    ref.watch(learningSurveyRemoteDataSourceProvider),
  ),
);
