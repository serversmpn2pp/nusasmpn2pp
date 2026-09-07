import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/profile/data/my_profile_remote_data_source.dart';
import 'package:nusa/features/profile/domain/my_profile.dart';

final class MyProfileRepository {
  MyProfileRepository(this._remote);

  final MyProfileRemoteDataSource _remote;

  Future<MyProfile> fetch() => _remote.fetch();

  Future<MyProfileUpdateResult> update(Map<String, dynamic> payload) =>
      _remote.update(payload);

  Future<MyProfile> updatePhoto(MyProfilePhotoFile file) =>
      _remote.updatePhoto(file);
}

final myProfileRepositoryProvider = Provider<MyProfileRepository>(
  (ref) => MyProfileRepository(ref.watch(myProfileRemoteDataSourceProvider)),
);
