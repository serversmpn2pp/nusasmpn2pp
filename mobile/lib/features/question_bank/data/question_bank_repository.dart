import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/question_bank/data/question_bank_remote_data_source.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';

class QuestionBankRepository {
  const QuestionBankRepository(this._remote);
  final QuestionBankRemoteDataSource _remote;

  Future<QuestionBankPage> fetch({
    required String query,
    required int? subjectId,
    required String grade,
    required String type,
    required String status,
    required int page,
  }) => _remote.fetch(
    query: query,
    subjectId: subjectId,
    grade: grade,
    type: type,
    status: status,
    page: page,
  );

  Future<BankQuestionDetail> detail(int id) => _remote.detail(id);
  Future<BankQuestionDetail> create(QuestionFormValue value) =>
      _remote.create(value);
  Future<BankQuestionDetail> update(int id, QuestionFormValue value) =>
      _remote.update(id, value);
  Future<void> archive(int id) => _remote.archive(id);
}

final questionBankRepositoryProvider = Provider<QuestionBankRepository>(
  (ref) =>
      QuestionBankRepository(ref.watch(questionBankRemoteDataSourceProvider)),
);
