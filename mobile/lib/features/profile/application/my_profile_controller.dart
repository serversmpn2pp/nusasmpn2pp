import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/features/auth/application/auth_controller.dart';
import 'package:nusa/features/profile/data/my_profile_repository.dart';
import 'package:nusa/features/profile/domain/my_profile.dart';

class MyProfileController extends AsyncNotifier<MyProfile> {
  @override
  Future<MyProfile> build() => _fetch();

  Future<void> refresh() async {
    state = const AsyncLoading<MyProfile>();
    state = await AsyncValue.guard(_fetch);
  }

  Future<String> saveProfile(Map<String, dynamic> payload) async {
    try {
      final result = await ref
          .read(myProfileRepositoryProvider)
          .update(payload);
      state = AsyncData(result.profile);
      ref.read(authControllerProvider.notifier).syncUser(result.user);
      return result.message;
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<void> updatePhoto(MyProfilePhotoFile file) async {
    try {
      final profile = await ref
          .read(myProfileRepositoryProvider)
          .updatePhoto(file);
      state = AsyncData(profile);
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }

  Future<MyProfile> _fetch() async {
    try {
      return await ref.read(myProfileRepositoryProvider).fetch();
    } on UnauthorizedException {
      await ref.read(authControllerProvider.notifier).logout();
      rethrow;
    }
  }
}

final myProfileControllerProvider =
    AsyncNotifierProvider.autoDispose<MyProfileController, MyProfile>(
      MyProfileController.new,
    );
