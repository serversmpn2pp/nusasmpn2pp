import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/employee_scan_status/application/employee_scan_status_controller.dart';
import 'package:nusa/features/employee_scan_status/presentation/widgets/employee_scan_status_components.dart';

class EmployeeScanStatusView extends ConsumerStatefulWidget {
  const EmployeeScanStatusView({super.key});

  @override
  ConsumerState<EmployeeScanStatusView> createState() =>
      _EmployeeScanStatusViewState();
}

class _EmployeeScanStatusViewState
    extends ConsumerState<EmployeeScanStatusView> {
  final _searchController = TextEditingController();
  Timer? _searchDebounce;
  Timer? _autoRefresh;

  @override
  void initState() {
    super.initState();
    _autoRefresh = Timer.periodic(const Duration(seconds: 15), (_) {
      if (!mounted) return;
      ref
          .read(employeeScanStatusControllerProvider.notifier)
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
    final status = ref.watch(employeeScanStatusControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text(
          'Status Scan Presensi Pegawai',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        actions: [
          IconButton(
            tooltip: 'Perbarui sekarang',
            onPressed: status.isLoading
                ? null
                : () => ref
                      .read(employeeScanStatusControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: status.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => EmployeeScanStatusError(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(employeeScanStatusControllerProvider.notifier)
                .refresh(),
          ),
          data: (dashboard) => RefreshIndicator(
            onRefresh: () => ref
                .read(employeeScanStatusControllerProvider.notifier)
                .refresh(),
            child: CustomScrollView(
              key: const PageStorageKey<String>('employee-scan-status-scroll'),
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
                    child: Column(
                      children: [
                        EmployeeScanServerCard(dashboard: dashboard),
                        const SizedBox(height: 10),
                        EmployeeScanSummaryGrid(summary: dashboard.summary),
                        const SizedBox(height: 10),
                        EmployeeScanHealthCard(summary: dashboard.summary),
                        const SizedBox(height: 10),
                        EmployeeScanFilters(
                          searchController: _searchController,
                          employeeTypes: dashboard.employeeTypes,
                          selectedEmployeeType: dashboard.selectedEmployeeType,
                          status: dashboard.status,
                          onSearchChanged: _search,
                          onEmployeeTypeChanged: (value) => ref
                              .read(
                                employeeScanStatusControllerProvider.notifier,
                              )
                              .filterEmployeeType(value),
                          onStatusChanged: (value) => ref
                              .read(
                                employeeScanStatusControllerProvider.notifier,
                              )
                              .filterStatus(value),
                          onClearSearch: _clearSearch,
                        ),
                        const SizedBox(height: 15),
                        EmployeeScanActivityHeader(
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
                    child: EmployeeScanStatusEmpty(),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                    sliver: SliverList.separated(
                      itemCount: dashboard.activities.length,
                      separatorBuilder: (context, index) =>
                          const SizedBox(height: 9),
                      itemBuilder: (context, index) => EmployeeScanActivityCard(
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
      ref.read(employeeScanStatusControllerProvider.notifier).search(value);
    });
  }

  void _clearSearch() {
    _searchDebounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(employeeScanStatusControllerProvider.notifier).search('');
  }
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Status scan presensi pegawai belum dapat dimuat.';
