import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/features/home/data/home_remote_data_source.dart';
import 'package:nusa/features/home/domain/home_dashboard.dart';

final class HomeRepository {
  HomeRepository(this._remote);

  final HomeRemoteDataSource _remote;

  Future<HomeDashboard> fetchDashboard() => _remote.fetchDashboard();
}

final homeRepositoryProvider = Provider<HomeRepository>((ref) {
  return HomeRepository(ref.watch(homeRemoteDataSourceProvider));
});
