import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/identity_photo/data/identity_photo_remote_data_source.dart';
import 'package:nusa/features/identity_photo/domain/identity_photo.dart';

final class IdentityPhotoRepository {
  IdentityPhotoRepository(this._remote);

  final IdentityPhotoRemoteDataSource _remote;

  Future<IdentityPhotoPage> fetch({
    required String tab,
    int? academicYearId,
    int? classId,
    required String photoStatus,
    required String employeeStatus,
    required String employeeType,
    required String query,
    required int page,
  }) => _remote.fetch(
    tab: tab,
    academicYearId: academicYearId,
    classId: classId,
    photoStatus: photoStatus,
    employeeStatus: employeeStatus,
    employeeType: employeeType,
    query: query,
    page: page,
  );

  Future<String> upload({
    required String tab,
    required int personId,
    required IdentityPhotoPickedFile file,
  }) => _remote.upload(tab: tab, personId: personId, file: file);
}

final identityPhotoRepositoryProvider = Provider<IdentityPhotoRepository>(
  (ref) => IdentityPhotoRepository(
    ref.watch(identityPhotoRemoteDataSourceProvider),
  ),
);
