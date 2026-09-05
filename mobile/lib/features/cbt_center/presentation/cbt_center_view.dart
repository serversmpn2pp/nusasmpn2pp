import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/cbt_center/application/cbt_center_controller.dart';
import 'package:nusa/features/cbt_center/domain/cbt_center.dart';

class CbtCenterView extends ConsumerWidget {
  const CbtCenterView({required this.focus, super.key});

  final CbtCenterFocus focus;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(cbtCenterControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: Text(_title),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(cbtCenterControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: state.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: error is AppException
                ? error.message
                : 'Pusat CBT belum dapat dimuat.',
            onRetry: ref.read(cbtCenterControllerProvider.notifier).refresh,
          ),
          data: (data) => RefreshIndicator(
            onRefresh: ref.read(cbtCenterControllerProvider.notifier).refresh,
            child: ListView(
              key: PageStorageKey<String>('cbt-center-${focus.name}'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: _sections(data),
            ),
          ),
        ),
      ),
    );
  }

  String get _title => switch (focus) {
    CbtCenterFocus.management => 'Pusat CBT',
    CbtCenterFocus.supervisor => 'Tugas Pengawas Saya',
    CbtCenterFocus.student => 'Ujian Saya',
  };

  List<Widget> _sections(CbtCenterData data) {
    final sections = <Widget>[
      _HeroCard(focus: focus, access: data.access),
      const SizedBox(height: 14),
    ];

    void management() {
      if (data.management == null) return;
      sections.add(_ManagementSection(data: data.management!));
      sections.add(const SizedBox(height: 16));
    }

    void supervisor() {
      if (data.supervisor == null) return;
      sections.add(_SupervisorSection(data: data.supervisor!));
      sections.add(const SizedBox(height: 16));
    }

    void student() {
      if (data.student == null) return;
      sections.add(_StudentSection(data: data.student!));
      sections.add(const SizedBox(height: 16));
    }

    switch (focus) {
      case CbtCenterFocus.management:
        management();
        supervisor();
        student();
      case CbtCenterFocus.supervisor:
        supervisor();
        management();
        student();
      case CbtCenterFocus.student:
        student();
        management();
        supervisor();
    }

    return sections;
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.focus, required this.access});

  final CbtCenterFocus focus;
  final CbtAccess access;

  @override
  Widget build(BuildContext context) {
    final (icon, title, description) = switch (focus) {
      CbtCenterFocus.management => (
        Icons.quiz_rounded,
        'Satu pintu Ujian & Asesmen',
        'Ringkasan dan pintasan CBT yang mengikuti hak akses akun Anda.',
      ),
      CbtCenterFocus.supervisor => (
        Icons.assignment_ind_rounded,
        'Tugas pengawas terpusat',
        'Lihat jadwal, ruang, peran, peserta, dan kesiapan bukti pengawasan.',
      ),
      CbtCenterFocus.student => (
        Icons.fact_check_rounded,
        'Daftar ujian siswa',
        'Pantau ujian aktif, akan datang, dan riwayat ujian yang telah selesai.',
      ),
    };

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [NusaColors.primary, NusaColors.primaryDark],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: NusaColors.primary.withValues(alpha: 0.18),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Stack(
        children: [
          Positioned(
            right: -20,
            top: -28,
            child: Icon(
              Icons.hub_rounded,
              size: 118,
              color: Colors.white.withValues(alpha: 0.06),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(15),
                      border: Border.all(
                        color: NusaColors.accent.withValues(alpha: 0.7),
                      ),
                    ),
                    child: Icon(icon, color: NusaColors.accent, size: 27),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          title,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          description,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 11.5,
                            height: 1.4,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  if (access.canManage) const _AccessChip(label: 'Pengelola'),
                  if (access.hasSupervisorTasks)
                    const _AccessChip(label: 'Pengawas'),
                  if (access.isStudent) const _AccessChip(label: 'Siswa'),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _AccessChip extends StatelessWidget {
  const _AccessChip({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: Colors.white.withValues(alpha: 0.11),
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: Colors.white24),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: Colors.white,
        fontSize: 10,
        fontWeight: FontWeight.w700,
      ),
    ),
  );
}

class _ManagementSection extends StatelessWidget {
  const _ManagementSection({required this.data});
  final CbtManagement data;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const _SectionTitle(
        title: 'Ringkasan Pengelolaan',
        subtitle: 'Kondisi data CBT saat ini',
      ),
      const SizedBox(height: 9),
      LayoutBuilder(
        builder: (context, constraints) {
          final width = (constraints.maxWidth - 10) / 2;
          return Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MetricCard(
                width: width,
                icon: Icons.library_books_rounded,
                label: 'Soal siap',
                value: data.summary.readyQuestions,
                color: NusaColors.primaryLight,
              ),
              _MetricCard(
                width: width,
                icon: Icons.account_tree_rounded,
                label: 'Ujian terpusat',
                value: data.summary.centralActivities,
                color: const Color(0xFFEFAF08),
              ),
              _MetricCard(
                width: width,
                icon: Icons.class_rounded,
                label: 'Asesmen kelas',
                value: data.summary.classAssessments,
                color: NusaColors.success,
              ),
              _MetricCard(
                width: width,
                icon: Icons.event_available_rounded,
                label: 'Paket terjadwal',
                value: data.summary.scheduledPackages,
                color: const Color(0xFF7A56B3),
              ),
            ],
          );
        },
      ),
      const SizedBox(height: 18),
      const _SectionTitle(
        title: 'Alur CBT',
        subtitle: 'Dua jalur ujian yang sudah digunakan NUSA',
      ),
      const SizedBox(height: 9),
      ...data.flows.map(
        (flow) => Padding(
          padding: const EdgeInsets.only(bottom: 9),
          child: _FlowCard(flow: flow),
        ),
      ),
      const SizedBox(height: 9),
      const _SectionTitle(
        title: 'Alat Pengelolaan',
        subtitle: 'Pintasan ditampilkan sesuai izin akun',
      ),
      const SizedBox(height: 9),
      if (data.tools.isEmpty)
        const _EmptyCard(
          icon: Icons.lock_outline_rounded,
          title: 'Tidak ada alat pengelolaan',
          message: 'Hak akses akun belum mencakup alat CBT.',
        )
      else
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth < 350 ? 2 : 3;
            final width =
                (constraints.maxWidth - ((columns - 1) * 9)) / columns;
            return Wrap(
              spacing: 9,
              runSpacing: 9,
              children: data.tools
                  .map(
                    (tool) => _ToolCard(
                      width: width,
                      tool: tool,
                      onTap: () {
                        if (tool.route case final route?) {
                          context.push(route);
                        } else {
                          _showFoundationMessage(context, tool.label);
                        }
                      },
                    ),
                  )
                  .toList(),
            );
          },
        ),
      const SizedBox(height: 11),
      const _ScopeNotice(
        message: 'Bank Soal, Paket Soal, Asesmen Kelas, dan Pelaksanaan Ujian Terpusat sudah terhubung secara native.',
      ),
    ],
  );
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.width,
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  final double width;
  final IconData icon;
  final String label;
  final int value;
  final Color color;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    child: Card(
      child: Padding(
        padding: const EdgeInsets.all(13),
        child: Row(
          children: [
            Container(
              width: 37,
              height: 37,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.11),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: color, size: 20),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$value',
                    style: const TextStyle(
                      fontSize: 19,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  Text(
                    label,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 9.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _FlowCard extends StatelessWidget {
  const _FlowCard({required this.flow});
  final CbtFlow flow;

  @override
  Widget build(BuildContext context) {
    final yellow = flow.color == 'kuning';
    final color = yellow ? const Color(0xFFEFAF08) : NusaColors.primary;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(
                yellow ? Icons.account_tree_rounded : Icons.class_rounded,
                color: color,
              ),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    flow.title,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    flow.description,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                      height: 1.4,
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
}

class _ToolCard extends StatelessWidget {
  const _ToolCard({
    required this.width,
    required this.tool,
    required this.onTap,
  });

  final double width;
  final CbtTool tool;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    height: 104,
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: NusaColors.outline),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(10),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(_toolIcon(tool.code), color: NusaColors.primary, size: 26),
              const SizedBox(height: 7),
              Text(
                tool.label,
                maxLines: 2,
                textAlign: TextAlign.center,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                tool.status == 'tersedia' ? 'Tersedia' : 'Fondasi',
                style: TextStyle(
                  color: tool.status == 'tersedia'
                      ? NusaColors.success
                      : NusaColors.textSecondary,
                  fontSize: 8.5,
                  fontWeight: tool.status == 'tersedia'
                      ? FontWeight.w700
                      : FontWeight.normal,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _SupervisorSection extends StatelessWidget {
  const _SupervisorSection({required this.data});
  final CbtSupervisor data;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const _SectionTitle(
        title: 'Tugas Pengawas Saya',
        subtitle: 'Penugasan ruang ujian terpusat',
      ),
      const SizedBox(height: 9),
      _SummaryStrip(
        items: [
          ('Semua', data.summary.total, NusaColors.primary),
          ('Hari ini', data.summary.today, NusaColors.success),
          ('Perlu bukti', data.summary.needsEvidence, const Color(0xFFE59A00)),
        ],
      ),
      const SizedBox(height: 10),
      if (data.tasks.isEmpty)
        const _EmptyCard(
          icon: Icons.event_busy_rounded,
          title: 'Belum ada tugas pengawas',
          message: 'Penugasan ruang ujian akan tampil di sini.',
        )
      else
        ...data.tasks.map(
          (task) => Padding(
            padding: const EdgeInsets.only(bottom: 9),
            child: _SupervisorTaskCard(
              task: task,
              onTap:
                  data.nativeOperations && task.canOpen && task.roomId != null
                  ? () => context.push('/tugas-pengawas-ujian/${task.roomId}')
                  : null,
            ),
          ),
        ),
      if (!data.nativeOperations) ...[
        const SizedBox(height: 2),
        const _ScopeNotice(
          message: 'Saat ini halaman ini untuk pemantauan. Unggah dan pemeriksaan bukti ruang tetap melalui NUSA web.',
        ),
      ],
    ],
  );
}

class _SupervisorTaskCard extends StatelessWidget {
  const _SupervisorTaskCard({required this.task, required this.onTap});
  final CbtSupervisorTask task;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      key: Key('supervisor-task-${task.id}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    task.activity,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
                const SizedBox(width: 8),
                _StatusPill(label: task.role, tone: 'biru'),
              ],
            ),
            const SizedBox(height: 4),
            Text(
              task.subject,
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 10),
            Wrap(
              spacing: 12,
              runSpacing: 7,
              children: [
                _InlineInfo(
                  icon: Icons.calendar_today_rounded,
                  text: _dateLabel(task.date),
                ),
                _InlineInfo(
                  icon: Icons.schedule_rounded,
                  text: task.time ?? '-',
                ),
                _InlineInfo(icon: Icons.meeting_room_rounded, text: task.room),
                _InlineInfo(
                  icon: Icons.groups_rounded,
                  text: '${task.studentCount} peserta',
                ),
              ],
            ),
            const Divider(height: 20),
            Row(
              children: [
                const Icon(
                  Icons.attachment_rounded,
                  size: 16,
                  color: NusaColors.textSecondary,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    task.evidenceLabel,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ),
                if (onTap != null)
                  const Icon(
                    Icons.chevron_right_rounded,
                    color: NusaColors.primary,
                  ),
              ],
            ),
          ],
        ),
      ),
    ),
  );
}

class _StudentSection extends StatelessWidget {
  const _StudentSection({required this.data});
  final CbtStudent data;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const _SectionTitle(
        title: 'Ujian Saya',
        subtitle: 'Jadwal dan status ujian siswa',
      ),
      const SizedBox(height: 9),
      _SummaryStrip(
        items: [
          ('Aktif', data.summary.active, NusaColors.success),
          ('Akan datang', data.summary.upcoming, const Color(0xFFE59A00)),
          ('Selesai', data.summary.completed, NusaColors.primary),
        ],
      ),
      const SizedBox(height: 10),
      if (data.exams.isEmpty)
        const _EmptyCard(
          icon: Icons.quiz_outlined,
          title: 'Belum ada ujian',
          message: 'Ujian yang diberikan kepada siswa akan tampil di sini.',
        )
      else
        ...data.exams.map(
          (exam) => Padding(
            padding: const EdgeInsets.only(bottom: 9),
            child: _StudentExamCard(
              exam: exam,
              onTap: () => context.push('/ujian-saya/${exam.id}'),
            ),
          ),
        ),
      if (!data.nativeExamRunner) ...[
        const SizedBox(height: 2),
        const _ScopeNotice(
          message: 'Tahap ini menampilkan jadwal dan status. Pengerjaan soal masih menggunakan NUSA web sampai mesin ujian native selesai diamankan.',
        ),
      ],
    ],
  );
}

class _StudentExamCard extends StatelessWidget {
  const _StudentExamCard({required this.exam, required this.onTap});
  final CbtStudentExam exam;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = _toneColor(exam.statusTone);
    return Card(
      child: InkWell(
        key: Key('student-exam-${exam.id}'),
        onTap: exam.canOpen ? onTap : null,
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(Icons.quiz_rounded, color: color),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      exam.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      exam.subject,
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 9,
                      runSpacing: 6,
                      children: [
                        _StatusPill(
                          label: exam.statusLabel,
                          tone: exam.statusTone,
                        ),
                        if (exam.date != null)
                          _InlineInfo(
                            icon: Icons.calendar_today_rounded,
                            text: _dateLabel(exam.date),
                          ),
                        if (exam.time != null)
                          _InlineInfo(
                            icon: Icons.schedule_rounded,
                            text: exam.time!,
                          ),
                        if (exam.durationMinutes > 0)
                          _InlineInfo(
                            icon: Icons.timer_outlined,
                            text: '${exam.durationMinutes} menit',
                          ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 5),
              const Padding(
                padding: EdgeInsets.only(top: 10),
                child: Icon(
                  Icons.chevron_right_rounded,
                  color: NusaColors.textSecondary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SummaryStrip extends StatelessWidget {
  const _SummaryStrip({required this.items});
  final List<(String, int, Color)> items;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 12),
      child: Row(
        children: items
            .map(
              (item) => Expanded(
                child: Column(
                  children: [
                    Text(
                      '${item.$2}',
                      style: TextStyle(
                        color: item.$3,
                        fontSize: 19,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      item.$1,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 9.5,
                      ),
                    ),
                  ],
                ),
              ),
            )
            .toList(),
      ),
    ),
  );
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.title, required this.subtitle});
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 2),
      Text(
        subtitle,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 11),
      ),
    ],
  );
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.label, required this.tone});
  final String label;
  final String tone;

  @override
  Widget build(BuildContext context) {
    final color = _toneColor(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 9.5,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _InlineInfo extends StatelessWidget {
  const _InlineInfo({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 14, color: NusaColors.textSecondary),
      const SizedBox(width: 4),
      Text(
        text,
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _ScopeNotice extends StatelessWidget {
  const _ScopeNotice({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: NusaColors.primary.withValues(alpha: 0.13)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.info_outline_rounded,
          size: 18,
          color: NusaColors.primary,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            message,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 10.5,
              height: 1.4,
            ),
          ),
        ),
      ],
    ),
  );
}

class _EmptyCard extends StatelessWidget {
  const _EmptyCard({
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
      padding: const EdgeInsets.all(18),
      child: Column(
        children: [
          Icon(icon, size: 34, color: NusaColors.textSecondary),
          const SizedBox(height: 8),
          Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
          const SizedBox(height: 3),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
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
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 12),
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

IconData _toolIcon(String code) => switch (code) {
  'asesmen-kelas' => Icons.class_rounded,
  'bank-soal' => Icons.library_books_rounded,
  'ujian-terpusat' => Icons.account_tree_rounded,
  'hasil-ujian-terpusat' => Icons.fact_check_outlined,
  'paket-soal' => Icons.inventory_rounded,
  'presensi-ujian' => Icons.fact_check_rounded,
  _ => Icons.quiz_rounded,
};

Color _toneColor(String tone) => switch (tone) {
  'aktif' || 'selesai' => NusaColors.success,
  'bahaya' => Colors.red.shade700,
  'menunggu' => const Color(0xFFB57900),
  _ => NusaColors.primary,
};

String _dateLabel(String? value) {
  if (value == null || value.isEmpty) return '-';
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  const months = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
  ];
  return '${date.day} ${months[date.month - 1]} ${date.year}';
}

void _showFoundationMessage(BuildContext context, String label) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text('$label akan dilanjutkan sebagai modul native berikutnya.'),
    ),
  );
}
