import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_scan_status/application/student_scan_status_controller.dart';
import 'package:nusa/features/student_scan_status/presentation/widgets/student_scan_status_components.dart';

class StudentScanStatusView extends ConsumerStatefulWidget {
  const StudentScanStatusView({super.key});

  @override
  ConsumerState<StudentScanStatusView> createState() =>
      _StudentScanStatusViewState();
}

class _StudentScanStatusViewState extends ConsumerState<StudentScanStatusView> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;
  Timer? _autoRefresh;

  @override
  void initState() {
    super.initState();
    _autoRefresh = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!mounted) return;
      ref
          .read(studentScanStatusControllerProvider.notifier)
          .refresh(silent: true);
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _autoRefresh?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final status = ref.watch(studentScanStatusControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Status Scan Presensi Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui sekarang',
            onPressed: status.isLoading
                ? null
                : () => ref
                      .read(studentScanStatusControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: status.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => StudentScanStatusError(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(studentScanStatusControllerProvider.notifier)
                .refresh(),
          ),
          data: (dashboard) => RefreshIndicator(
            onRefresh: () => ref
                .read(studentScanStatusControllerProvider.notifier)
                .refresh(),
            child: CustomScrollView(
              key: const PageStorageKey<String>('student-scan-status-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                    child: Column(
                      children: [
                        ScanServerStatusCard(dashboard: dashboard),
                        const SizedBox(height: 10),
                        StudentScanSummaryGrid(summary: dashboard.summary),
                        const SizedBox(height: 10),
                        ScanActivityHealthCard(summary: dashboard.summary),
                        const SizedBox(height: 10),
                        StudentScanStatusFilters(
                          searchController: _searchController,
                          classes: dashboard.classes,
                          selectedClassId: dashboard.selectedClassId,
                          status: dashboard.status,
                          onSearchChanged: _search,
                          onClassChanged: (value) => ref
                              .read(
                                studentScanStatusControllerProvider.notifier,
                              )
                              .filterClass(value),
                          onStatusChanged: (value) => ref
                              .read(
                                studentScanStatusControllerProvider.notifier,
                              )
                              .filterStatus(value),
                          onClearSearch: _clearSearch,
                        ),
                        const SizedBox(height: 15),
                        ScanActivitySectionHeader(
                          count: dashboard.activities.length,
                        ),
                        const SizedBox(height: 9),
                      ],
                    ),
                  ),
                ),
                if (dashboard.activities.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: StudentScanStatusEmpty(),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                    sliver: SliverList.separated(
                      itemCount: dashboard.activities.length,
                      separatorBuilder: (context, index) =>
                          const SizedBox(height: 9),
                      itemBuilder: (context, index) => StudentScanActivityCard(
                        activity: dashboard.activities[index],
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 450), () {
      if (!mounted) return;
      ref.read(studentScanStatusControllerProvider.notifier).search(value);
    });
  }

  void _clearSearch() {
    _searchDebounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(studentScanStatusControllerProvider.notifier).search('');
  }
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Status scan presensi siswa belum dapat dimuat.';
