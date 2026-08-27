import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/learning_survey/data/learning_survey_repository.dart';
import 'package:nusa/features/learning_survey/domain/learning_survey.dart';

final learningSurveyProvider = FutureProvider.autoDispose
    .family<LearningSurveyPage, LearningSurveyArgs>((ref, args) async {
      try {
        return await ref.read(learningSurveyRepositoryProvider).fetch(args);
      } on UnauthorizedException {
        await ref.read(authControllerProvider.notifier).logout();
        rethrow;
      }
    });

final learningSurveyActionsProvider = Provider<LearningSurveyActions>(
  LearningSurveyActions.new,
);

class LearningSurveyActions {
  LearningSurveyActions(this._ref);

  final Ref _ref;

  Future<LearningSurveySubmitResult> submit(
    LearningSurveyArgs args,
    LearningSurveySubmission value,
  ) => _guard(
    () => _ref.read(learningSurveyRepositoryProvider).submit(args, value),
  );

  Future<T> _guard<T>(Future<T> Function() operation) async {
    try {
      return await operation();
    } on UnauthorizedException {
      await _ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}
