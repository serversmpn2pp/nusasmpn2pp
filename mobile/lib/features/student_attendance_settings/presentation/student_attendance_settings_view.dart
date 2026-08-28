import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_attendance_settings/application/student_attendance_settings_controller.dart';
import 'package:nusa/features/student_attendance_settings/domain/student_attendance_settings.dart';
import 'package:nusa/features/student_attendance_settings/presentation/widgets/student_attendance_settings_components.dart';
import 'package:nusa/features/student_attendance_settings/presentation/widgets/student_attendance_settings_form_sheet.dart';

class StudentAttendanceSettingsView extends ConsumerStatefulWidget {
  const StudentAttendanceSettingsView({super.key});

  @override
  ConsumerState<StudentAttendanceSettingsView> createState() =>
      _StudentAttendanceSettingsViewState();
}

class _StudentAttendanceSettingsViewState
    extends ConsumerState<StudentAttendanceSettingsView> {
  bool _mutating = false;

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(studentAttendanceSettingsControllerProvider);
    final current = settings.value;
    final canAdd =
        current?.canManage == true &&
        current!.summary.total < current.days.length;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Pengaturan Presensi Siswa'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: settings.isLoading
                ? null
                : () => ref
                      .read(
                        studentAttendanceSettingsControllerProvider.notifier,
                      )
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: canAdd
          ? FloatingActionButton.extended(
              key: const Key('add-student-attendance-setting'),
              onPressed: _mutating ? null : () => _openForm(current),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Hari'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
                child: Column(
                  children: [
                    AttendanceSettingsSummaryCard(summary: current.summary),
                    const SizedBox(height: 10),
                    AttendanceSettingsInfoBanner(
                      allConfigured: current.summary.unconfigured == 0,
                    ),
                    const SizedBox(height: 10),
                    AttendanceSettingsFilters(
                      days: current.days,
                      selectedDay: current.selectedDay,
                      status: current.status,
                      enabled: !settings.isLoading,
                      onDayChanged: (value) => ref
                          .read(
                            studentAttendanceSettingsControllerProvider
                                .notifier,
                          )
                          .filterDay(value),
                      onStatusChanged: (value) => ref
                          .read(
                            studentAttendanceSettingsControllerProvider
                                .notifier,
                          )
                          .filterStatus(value),
                    ),
                  ],
                ),
              ),
            Expanded(
              child: settings.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => AttendanceSettingsError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(
                        studentAttendanceSettingsControllerProvider.notifier,
                      )
                      .refresh(),
                ),
                data: (catalog) => AttendanceSettingsResults(
                  catalog: catalog,
                  onRefresh: () => ref
                      .read(
                        studentAttendanceSettingsControllerProvider.notifier,
                      )
                      .refresh(),
                  onEdit: catalog.canManage
                      ? (item) => _openForm(catalog, existing: item)
                      : null,
                  enabled: !_mutating,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openForm(
    StudentAttendanceSettingsCatalog catalog, {
    StudentAttendanceSetting? existing,
  }) async {
    final value =
        await showModalBottomSheet<StudentAttendanceSettingsFormValue>(
          context: context,
          isScrollControlled: true,
          useSafeArea: true,
          builder: (context) => StudentAttendanceSettingsFormSheet(
            days: catalog.days,
            existing: existing,
          ),
        );
    if (value == null || !mounted) return;

    await _runMutation(
      successMessage: existing == null
          ? 'Pengaturan presensi ${_dayLabel(catalog.days, value.day)} berhasil ditambahkan.'
          : 'Pengaturan presensi ${_dayLabel(catalog.days, value.day)} berhasil diperbarui.',
      operation: () => existing == null
          ? ref.read(studentAttendanceSettingsActionsProvider).create(value)
          : ref
                .read(studentAttendanceSettingsActionsProvider)
                .update(id: existing.id, value: value),
    );
  }

  Future<void> _runMutation({
    required String successMessage,
    required Future<void> Function() operation,
  }) async {
    setState(() => _mutating = true);
    try {
      await operation();
      await ref.read(studentAttendanceSettingsControllerProvider.future);
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(successMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }
}

String _dayLabel(List<AttendanceDay> days, String code) =>
    days.where((day) => day.code == code).map((day) => day.label).firstOrNull ??
    code;

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Pengaturan presensi siswa belum dapat diproses.';
}
