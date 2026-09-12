import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/my_grades/domain/my_grades.dart';
import 'package:nusa/features/my_grades/presentation/widgets/my_grades_components.dart';
import 'package:nusa/features/parent_child_academics/application/parent_child_academics_controller.dart';
import 'package:nusa/features/parent_child_academics/domain/parent_child_academics.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class ParentChildAcademicsView extends StatelessWidget {
  const ParentChildAcademicsView({
    this.initialTab = ParentChildAcademicsTab.schedule,
    this.initialSemester = 'ganjil',
    this.pageTitle = 'Akademik Anak',
    this.showTabs = true,
    super.key,
  });

  final ParentChildAcademicsTab initialTab;
  final String initialSemester;
  final String pageTitle;
  final bool showTabs;

  @override
  Widget build(BuildContext context) => ProviderScope(
    overrides: [
      parentChildAcademicsInitialTabProvider.overrideWithValue(initialTab),
      parentChildAcademicsInitialSemesterProvider.overrideWithValue(
        initialSemester,
      ),
    ],
    child: _ParentChildAcademicsScreen(
      pageTitle: pageTitle,
      showTabs: showTabs,
    ),
  );
}

class _ParentChildAcademicsScreen extends ConsumerWidget {
  const _ParentChildAcademicsScreen({
    required this.pageTitle,
    required this.showTabs,
  });

  final String pageTitle;
  final bool showTabs;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final result = ref.watch(parentChildAcademicsControllerProvider);
    final controller = ref.read(
      parentChildAcademicsControllerProvider.notifier,
    );
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(pageTitle),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: result.isLoading ? null : controller.refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: result.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, _) => _ErrorState(
            message: _message(error),
            onRetry: controller.refresh,
          ),
          data: (page) =>
              _Content(page: page, pageTitle: pageTitle, showTabs: showTabs),
        ),
      ),
    );
  }
}

class _Content extends ConsumerWidget {
  const _Content({
    required this.page,
    required this.pageTitle,
    required this.showTabs,
  });

  final ParentChildAcademicsPage page;
  final String pageTitle;
  final bool showTabs;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(
      parentChildAcademicsControllerProvider.notifier,
    );
    return RefreshIndicator(
      onRefresh: controller.refresh,
      child: ListView(
        key: const Key('parent-child-academics-scroll'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
        children: [
          _Hero(page: page, pageTitle: pageTitle),
          if (page.grades.student == null) ...[
            const SizedBox(height: 12),
            const _EmptyState(
              icon: Icons.link_off_rounded,
              title: 'Akun belum terhubung ke data anak',
              message:
                  'Hubungi administrator sekolah agar akun orang tua '
                  'dihubungkan dengan data anak yang benar.',
            ),
          ] else ...[
            if (page.children.length > 1) ...[
              const SizedBox(height: 12),
              NusaDropdownField<int>(
                fieldKey: const Key('parent-academics-child-filter'),
                value: page.selectedStudentId,
                options: page.children
                    .map(
                      (child) => NusaDropdownOption<int>(
                        value: child.id,
                        label: child.name,
                      ),
                    )
                    .toList(growable: false),
                decoration: const InputDecoration(
                  labelText: 'Pilih anak',
                  prefixIcon: Icon(Icons.family_restroom_rounded),
                ),
                onChanged: (value) {
                  if (value != null) controller.selectStudent(value);
                },
              ),
            ],
            if (showTabs) ...[
              const SizedBox(height: 12),
              _Tabs(selected: page.tab, onChanged: controller.selectTab),
            ],
            const SizedBox(height: 12),
            if (page.tab == ParentChildAcademicsTab.schedule)
              _ScheduleSection(page: page)
            else
              _GradesSection(page: page),
          ],
        ],
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.page, required this.pageTitle});

  final ParentChildAcademicsPage page;
  final String pageTitle;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: .16),
          blurRadius: 16,
          offset: const Offset(0, 7),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .13),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(
            Icons.auto_stories_rounded,
            color: NusaColors.accent,
            size: 27,
          ),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                pageTitle,
                style: const TextStyle(color: Colors.white70, fontSize: 10.5),
              ),
              const SizedBox(height: 3),
              Text(
                page.grades.student?.name ?? 'Data anak belum tersedia',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                [
                  page.grades.schoolClass?.name,
                  page.grades.selectedAcademicYear?.name,
                ].whereType<String>().join(' · '),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.selected, required this.onChanged});

  final ParentChildAcademicsTab selected;
  final ValueChanged<ParentChildAcademicsTab> onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(4),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(15),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        Expanded(
          child: _TabButton(
            key: const Key('parent-academics-schedule-tab'),
            icon: Icons.calendar_view_week_rounded,
            label: 'Jadwal Pelajaran',
            selected: selected == ParentChildAcademicsTab.schedule,
            onTap: () => onChanged(ParentChildAcademicsTab.schedule),
          ),
        ),
        Expanded(
          child: _TabButton(
            key: const Key('parent-academics-grades-tab'),
            icon: Icons.workspace_premium_rounded,
            label: 'Nilai Anak',
            selected: selected == ParentChildAcademicsTab.grades,
            onTap: () => onChanged(ParentChildAcademicsTab.grades),
          ),
        ),
      ],
    ),
  );
}

class _TabButton extends StatelessWidget {
  const _TabButton({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: selected ? NusaColors.primary : Colors.transparent,
    borderRadius: BorderRadius.circular(11),
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(11),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 10),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              size: 16,
              color: selected ? Colors.white : NusaColors.textSecondary,
            ),
            const SizedBox(width: 6),
            Flexible(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: selected ? Colors.white : NusaColors.textSecondary,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _ScheduleSection extends ConsumerWidget {
  const _ScheduleSection({required this.page});

  final ParentChildAcademicsPage page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final controller = ref.read(
      parentChildAcademicsControllerProvider.notifier,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (page.grades.academicYears.isNotEmpty) ...[
          NusaDropdownField<int>(
            fieldKey: const Key('parent-academics-year-filter'),
            value: page.grades.filter.academicYearId,
            options: page.grades.academicYears
                .map(
                  (year) => NusaDropdownOption<int>(
                    value: year.id,
                    label: year.label,
                  ),
                )
                .toList(growable: false),
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran',
              prefixIcon: Icon(Icons.school_outlined),
            ),
            onChanged: controller.selectAcademicYear,
          ),
          const SizedBox(height: 10),
        ],
        _ScheduleSummary(summary: page.schedule.summary),
        const SizedBox(height: 12),
        if (page.schedule.emptyMessage != null)
          _EmptyState(
            icon: Icons.event_busy_rounded,
            title: 'Jadwal belum tersedia',
            message: page.schedule.emptyMessage!,
          )
        else
          _ScheduleDays(schedule: page.schedule),
      ],
    );
  }
}

class _ScheduleSummary extends StatelessWidget {
  const _ScheduleSummary({required this.summary});

  final ChildScheduleSummary summary;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 14),
      child: Row(
        children: [
          _Stat(label: 'Kelas', value: summary.schoolClass ?? '-'),
          _Stat(label: 'Jam terjadwal', value: '${summary.scheduledPeriods}'),
          _Stat(label: 'Mata pelajaran', value: '${summary.subjects}'),
        ],
      ),
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 17,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 8.5,
          ),
        ),
      ],
    ),
  );
}

class _ScheduleDays extends StatefulWidget {
  const _ScheduleDays({required this.schedule});

  final ChildSchedule schedule;

  @override
  State<_ScheduleDays> createState() => _ScheduleDaysState();
}

class _ScheduleDaysState extends State<_ScheduleDays> {
  late String _selectedDay;

  @override
  void initState() {
    super.initState();
    _selectedDay = _initialDay();
  }

  @override
  void didUpdateWidget(covariant _ScheduleDays oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (!widget.schedule.days.any((day) => day.code == _selectedDay)) {
      _selectedDay = _initialDay();
    }
  }

  String _initialDay() =>
      widget.schedule.days
          .where((day) => day.code == widget.schedule.today)
          .map((day) => day.code)
          .firstOrNull ??
      (widget.schedule.days.isEmpty
          ? 'senin'
          : widget.schedule.days.first.code);

  @override
  Widget build(BuildContext context) {
    final selected = widget.schedule.days
        .where((day) => day.code == _selectedDay)
        .firstOrNull;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              for (final day in widget.schedule.days) ...[
                ChoiceChip(
                  key: Key('parent-academics-day-${day.code}'),
                  label: Text(
                    day.isToday ? '${day.label} · Hari ini' : day.label,
                  ),
                  selected: day.code == _selectedDay,
                  onSelected: (_) => setState(() => _selectedDay = day.code),
                ),
                const SizedBox(width: 7),
              ],
            ],
          ),
        ),
        const SizedBox(height: 10),
        if (selected == null || selected.items.isEmpty)
          const _EmptyState(
            icon: Icons.calendar_today_outlined,
            title: 'Belum ada jam pelajaran',
            message: 'Jam pelajaran untuk hari ini belum diatur.',
          )
        else
          for (var index = 0; index < selected.items.length; index++) ...[
            _ScheduleCard(item: selected.items[index]),
            if (index < selected.items.length - 1) const SizedBox(height: 8),
          ],
      ],
    );
  }
}

class _ScheduleCard extends StatelessWidget {
  const _ScheduleCard({required this.item});

  final ChildScheduleItem item;

  @override
  Widget build(BuildContext context) {
    final special = !item.isLesson;
    final color = special ? const Color(0xFF9A7100) : NusaColors.primary;
    return Card(
      key: Key('parent-academics-schedule-${item.id}'),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(17),
        side: BorderSide(
          color: item.current ? NusaColors.accent : NusaColors.outline,
          width: item.current ? 1.5 : 1,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 58,
              padding: const EdgeInsets.symmetric(vertical: 8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: .09),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Column(
                children: [
                  Text(
                    'Jam ${item.periodNumber}',
                    style: TextStyle(
                      color: color,
                      fontSize: 9,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(item.startTime, style: const TextStyle(fontSize: 10.5)),
                ],
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      Text(
                        item.label,
                        style: const TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 12.5,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (item.current) const _Badge(label: 'Berlangsung'),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${item.startTime} - ${item.endTime}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9.5,
                    ),
                  ),
                  if (item.teacherName != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      item.teacherName!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  const _Badge({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      color: NusaColors.accent.withValues(alpha: .18),
      borderRadius: BorderRadius.circular(99),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: Color(0xFF725600),
        fontSize: 8,
        fontWeight: FontWeight.w900,
      ),
    ),
  );
}

class _GradesSection extends ConsumerWidget {
  const _GradesSection({required this.page});

  final ParentChildAcademicsPage page;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final grades = page.grades;
    final controller = ref.read(
      parentChildAcademicsControllerProvider.notifier,
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        MyGradesFilters(
          page: grades,
          enabled: grades.academicYears.isNotEmpty,
          onAcademicYearChanged: controller.selectAcademicYear,
          onSemesterChanged: controller.selectSemester,
        ),
        const SizedBox(height: 10),
        MyGradesSummaryCard(summary: grades.summary),
        const SizedBox(height: 10),
        _Notice(message: page.gradeNotice),
        const SizedBox(height: 16),
        Row(
          children: [
            const Expanded(
              child: Text(
                'Nilai Mata Pelajaran',
                style: TextStyle(
                  color: NusaColors.textPrimary,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Text(
              grades.filter.semester == 'ganjil' ? 'Ganjil' : 'Genap',
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 10,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
        const SizedBox(height: 9),
        if (grades.subjects.isEmpty)
          _EmptyState(
            icon: Icons.workspace_premium_outlined,
            title: 'Nilai belum tersedia',
            message:
                grades.emptyMessage ??
                'Belum ada nilai yang dipublikasikan untuk semester ini.',
          )
        else
          for (var index = 0; index < grades.subjects.length; index++) ...[
            if (grades.subjects[index].open)
              OpenGradeSubjectCard(
                subject: grades.subjects[index],
                initiallyExpanded: index == 0,
              )
            else
              _ParentLockedGradeCard(subject: grades.subjects[index]),
            if (index < grades.subjects.length - 1) const SizedBox(height: 9),
          ],
      ],
    );
  }
}

class _ParentLockedGradeCard extends StatelessWidget {
  const _ParentLockedGradeCard({required this.subject});

  final MyGradesSubject subject;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('parent-locked-grade-${subject.assignmentId}'),
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(18),
      side: BorderSide(color: NusaColors.accent.withValues(alpha: .65)),
    ),
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: const Color(0xFFFFF9E5),
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.lock_outline_rounded,
              color: Color(0xFF9A7100),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  subject.subjectName,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  subject.teacherName,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 9.5,
                  ),
                ),
                const SizedBox(height: 7),
                const Text(
                  'Menunggu survei anak. Nilai belum dapat dilihat dari '
                  'akun orang tua.',
                  style: TextStyle(
                    color: Color(0xFF725600),
                    fontSize: 10.5,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: const Color(0xFFFFF8D8),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.info_outline_rounded,
          size: 18,
          color: Color(0xFF9A7100),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: Color(0xFF725600),
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.icon,
    required this.title,
    required this.message,
  });

  final IconData icon;
  final String title;
  final String message;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          Icon(icon, size: 42, color: NusaColors.primary),
          const SizedBox(height: 10),
          Text(title, style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ],
      ),
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(Icons.cloud_off_rounded, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba Lagi'),
          ),
        ],
      ),
    ),
  );
}

String _message(Object error) => switch (error) {
  AppException exception => exception.message,
  _ => 'Akademik anak belum dapat dimuat.',
};
