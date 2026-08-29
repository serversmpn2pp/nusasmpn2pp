import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/private_confirmation/data/private_confirmation_remote_data_source.dart';
import 'package:nusa/features/private_confirmation/domain/private_confirmation.dart';

final class PrivateConfirmationRepository {
  PrivateConfirmationRepository(this._remote);

  final PrivateConfirmationRemoteDataSource _remote;

  Future<PrivateConfirmationPage> fetch({
    required String query,
    required int? classId,
    required int page,
  }) => _remote.fetch(query: query, classId: classId, page: page);

  Future<PrivateConfirmationDetail> fetchDetail(int periodId) =>
      _remote.fetchDetail(periodId);

  Future<PrivateConfirmationUpdateResult> update({
    required int periodId,
    required String result,
    required int? reminderDays,
    required String? privateNote,
  }) => _remote.update(
    periodId: periodId,
    result: result,
    reminderDays: reminderDays,
    privateNote: privateNote,
  );
}

final privateConfirmationRepositoryProvider =
    Provider<PrivateConfirmationRepository>(
      (ref) => PrivateConfirmationRepository(
        ref.watch(privateConfirmationRemoteDataSourceProvider),
      ),
    );
