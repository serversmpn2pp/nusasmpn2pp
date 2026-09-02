import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_guardian_students/application/my_guardian_student_controller.dart';
import 'package:nusa/features/my_guardian_students/domain/my_guardian_student.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class MyGuardianStudentListView extends ConsumerStatefulWidget {
  const MyGuardianStudentListView({super.key});

  @override
  ConsumerState<MyGuardianStudentListView> createState() =>
      _MyGuardianStudentListViewState();
}

class _MyGuardianStudentListViewState
    extends ConsumerState<MyGuardianStudentListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(myGuardianStudentControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Siswa Wali Saya'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _ContextHeader(page: current),
                    const SizedBox(height: 9),
                    TextField(
                      key: const Key('my-guardian-student-search'),
                      controller: _search,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(myGuardianStudentControllerProvider.notifier)
                          .search,
                      decoration: const InputDecoration(
                        hintText: 'Cari nama, NIS, atau NISN',
                        prefixIcon: Icon(Icons.search_rounded),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: NusaDropdownField<int?>(
                            fieldKey: const Key(
                              'my-guardian-student-grade-filter',
                            ),
                            value: current.filter.grade,
                            enabled: !state.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Tingkat',
                            ),
                            options: [
                              const NusaDropdownOption(
                                value: null,
                                label: 'Semua',
                              ),
                              for (final item in current.options.grades)
                                NusaDropdownOption(
                                  value: item.value,
                                  label: item.label,
                                ),
                            ],
                            onChanged: ref
                                .read(
                                  myGuardianStudentControllerProvider.notifier,
                                )
                                .filterGrade,
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: NusaDropdownField<int?>(
                            fieldKey: const Key(
                              'my-guardian-student-class-filter',
                            ),
                            value: current.filter.classId,
                            enabled: !state.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Kelas',
                            ),
                            options: [
                              const NusaDropdownOption(
                                value: null,
                                label: 'Semua',
                              ),
                              for (final item in current.options.classes.where(
                                (item) =>
                                    current.filter.grade == null ||
                                    item.grade == current.filter.grade,
                              ))
                                NusaDropdownOption(
                                  value: item.id,
                                  label: item.name,
                                ),
                            ],
                            onChanged: ref
                                .read(
                                  myGuardianStudentControllerProvider.notifier,
                                )
                                .filterClass,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            Expanded(
              child: state.when(
                loading: () => current == null
                    ? const Center(child: CircularProgressIndicator())
                    : _Results(
                        page: current,
                        loadingMore: _loadingMore,
                        onRefresh: _refresh,
                        onLoadMore: _loadMore,
                      ),
                error: (error, stackTrace) =>
                    _Error(message: _message(error), onRetry: _refresh),
                data: (page) => _Results(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: _refresh,
                  onLoadMore: _loadMore,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _refresh() =>
      ref.read(myGuardianStudentControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(myGuardianStudentControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(_message(error))));
      }
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _ContextHeader extends StatelessWidget {
  const _ContextHeader({required this.page});
  final MyGuardianStudentPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Icon(
                Icons.supervised_user_circle_rounded,
                color: NusaColors.accent,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Siswa dampingan aktif',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    page.academicYear == null
                        ? 'Tahun pelajaran aktif belum tersedia'
                        : 'Tahun pelajaran ${page.academicYear!.name}',
                    style: const TextStyle(color: Colors.white70, fontSize: 10),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            _SummaryValue(label: 'Siswa', value: '${page.summary.students}'),
            _SummaryValue(label: 'Kelas', value: '${page.summary.classes}'),
            _SummaryValue(
              label: 'L / P',
              value: '${page.summary.male} / ${page.summary.female}',
            ),
            _SummaryValue(
              label: 'Berpoin',
              value: '${page.summary.withPoints}',
              accent: true,
            ),
          ],
        ),
      ],
    ),
  );
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({
    required this.label,
    required this.value,
    this.accent = false,
  });
  final String label;
  final String value;
  final bool accent;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: accent ? NusaColors.accent : Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          style: const TextStyle(color: Colors.white70, fontSize: 8.5),
        ),
      ],
    ),
  );
}

class _Results extends StatelessWidget {
  const _Results({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
  });
  final MyGuardianStudentPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) => page.items.isEmpty
      ? RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(28, 42, 28, 30),
            children: [
              const Icon(
                Icons.people_outline_rounded,
                size: 54,
                color: NusaColors.textSecondary,
              ),
              const SizedBox(height: 12),
              Text(
                page.summary.students == 0
                    ? 'Admin belum menugaskan siswa kepada akun Guru Wali ini.'
                    : 'Tidak ada siswa yang sesuai dengan pencarian atau filter.',
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ],
          ),
        )
      : RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 3, 16, 26),
            itemCount:
                page.items.length +
                (page.pagination.hasNextPage || loadingMore ? 1 : 0),
            itemBuilder: (context, index) {
              if (index >= page.items.length) {
                if (!loadingMore) {
                  WidgetsBinding.instance.addPostFrameCallback(
                    (_) => onLoadMore(),
                  );
                }
                return const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              return Padding(
                padding: const EdgeInsets.only(bottom: 9),
                child: _StudentCard(item: page.items[index]),
              );
            },
          ),
        );
}

class _StudentCard extends StatelessWidget {
  const _StudentCard({required this.item});
  final MyGuardianStudentItem item;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      key: Key('my-guardian-student-${item.id}'),
      onTap: () => context.push('/siswa-wali-saya/${item.id}'),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _Avatar(name: item.name, photoUrl: item.photoUrl, size: 50),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${item.schoolClass?.name ?? 'Belum ditempatkan'} · NISN ${item.nisn ?? '-'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 5,
                    children: [
                      _Badge(
                        label: item.genderLabel,
                        color: NusaColors.primary,
                      ),
                      _Badge(
                        label: '${item.reportCount} laporan',
                        color: NusaColors.textSecondary,
                      ),
                      _Badge(
                        label: 'Mulai ${_date(item.assignmentStartDate)}',
                        color: NusaColors.primaryLight,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 7),
            Column(
              children: [
                Text(
                  '${item.totalPoints}',
                  style: TextStyle(
                    color: item.totalPoints > 0
                        ? const Color(0xFFC58F00)
                        : const Color(0xFF2E9C50),
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const Text(
                  'poin',
                  style: TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 8,
                  ),
                ),
                const SizedBox(height: 7),
                const Icon(
                  Icons.chevron_right_rounded,
                  color: NusaColors.textSecondary,
                ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _Avatar extends StatelessWidget {
  const _Avatar({
    required this.name,
    required this.photoUrl,
    required this.size,
  });
  final String name;
  final String? photoUrl;
  final double size;

  @override
  Widget build(BuildContext context) {
    final image = photoUrl == null || photoUrl!.isEmpty
        ? null
        : NetworkImage(photoUrl!);
    return CircleAvatar(
      radius: size / 2,
      backgroundColor: NusaColors.surfaceBlue,
      backgroundImage: image,
      child: image == null
          ? Text(
              name.isEmpty ? '?' : name.substring(0, 1).toUpperCase(),
              style: const TextStyle(
                color: NusaColors.primary,
                fontWeight: FontWeight.w900,
              ),
            )
          : null,
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(999),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: color,
        fontSize: 8.5,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _Error extends StatelessWidget {
  const _Error({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48),
          const SizedBox(height: 10),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonal(
            onPressed: onRetry,
            child: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _date(String? value) {
  final date = value == null ? null : DateTime.tryParse(value);
  if (date == null) return '-';
  return '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _message(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Data siswa wali belum dapat dimuat.',
};
