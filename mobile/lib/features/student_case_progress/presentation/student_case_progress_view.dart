import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_case_progress/application/student_case_progress_controller.dart';
import 'package:nusa/features/student_case_progress/domain/student_case_progress.dart';
import 'package:nusa/features/student_case_progress/presentation/student_case_style.dart';

class StudentCaseProgressView extends ConsumerStatefulWidget {
  const StudentCaseProgressView({super.key});

  @override
  ConsumerState<StudentCaseProgressView> createState() =>
      _StudentCaseProgressViewState();
}

class _StudentCaseProgressViewState
    extends ConsumerState<StudentCaseProgressView> {
  bool _loadingMore = false;

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentCaseProgressControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Progress Kasus Saya'),
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
        child: state.when(
          loading: () => current == null
              ? const Center(child: CircularProgressIndicator())
              : _Content(
                  page: current,
                  loadingMore: _loadingMore,
                  onRefresh: _refresh,
                  onLoadMore: _loadMore,
                  onOpen: _open,
                ),
          error: (error, stackTrace) =>
              _ErrorState(message: _message(error), onRetry: _refresh),
          data: (page) => _Content(
            page: page,
            loadingMore: _loadingMore,
            onRefresh: _refresh,
            onLoadMore: _loadMore,
            onOpen: _open,
          ),
        ),
      ),
    );
  }

  Future<void> _refresh() =>
      ref.read(studentCaseProgressControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentCaseProgressControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _snack(_message(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  void _open(StudentCaseItem item) {
    context.push('/progress-kasus-saya/${item.id}');
  }

  void _snack(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
}

class _Content extends StatelessWidget {
  const _Content({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onOpen,
  });

  final StudentCaseProgressPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;
  final ValueChanged<StudentCaseItem> onOpen;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    child: ListView.builder(
      key: const Key('student-case-progress-scroll'),
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
      itemCount: _itemCount,
      itemBuilder: (context, index) {
        if (index == 0) return _Hero(page: page);
        if (index == 1) return const SizedBox(height: 12);
        if (index == 2) return _Summary(summary: page.summary);
        if (index == 3) return const SizedBox(height: 18);
        if (index == 4) {
          return const _SectionTitle(
            title: 'Perkembangan Kasus',
            subtitle: 'Informasi yang telah disiapkan sekolah untuk Anda.',
          );
        }
        if (index == 5) return const SizedBox(height: 10);

        if (page.student == null) return const _UnlinkedAccount();
        if (page.items.isEmpty) return const _EmptyCases();
        final itemIndex = index - 6;
        if (itemIndex < page.items.length) {
          return Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: _CaseCard(item: page.items[itemIndex], onTap: onOpen),
          );
        }
        if (!loadingMore) {
          WidgetsBinding.instance.addPostFrameCallback((_) => onLoadMore());
        }
        return const Padding(
          padding: EdgeInsets.all(16),
          child: Center(child: CircularProgressIndicator()),
        );
      },
    ),
  );

  int get _itemCount {
    if (page.student == null || page.items.isEmpty) return 7;
    return 6 +
        page.items.length +
        (page.pagination.hasNextPage || loadingMore ? 1 : 0);
  }
}

class _Hero extends StatelessWidget {
  const _Hero({required this.page});
  final StudentCaseProgressPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 47,
          height: 47,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .12),
            borderRadius: BorderRadius.circular(15),
          ),
          child: const Icon(
            Icons.route_rounded,
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
                page.student?.name ?? 'Akun siswa belum terhubung',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Pantau pemeriksaan, keputusan sekolah, dan tindak lanjut secara aman.',
                style: TextStyle(
                  color: Colors.white70,
                  height: 1.35,
                  fontSize: 11,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                page.activeAcademicYear?.name ?? 'Semua tahun pelajaran',
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 11,
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

class _Summary extends StatelessWidget {
  const _Summary({required this.summary});
  final StudentCaseSummary summary;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 13),
      child: Row(
        children: [
          _Stat(value: summary.all, label: 'Semua'),
          _Stat(
            value: summary.inProgress,
            label: 'Diproses',
            color: const Color(0xFF9A7100),
          ),
          _Stat(
            value: summary.guidance,
            label: 'Pembinaan',
            color: NusaColors.success,
          ),
          _Stat(
            value: summary.officialPoints,
            label: 'Poin Resmi',
            color: const Color(0xFFC53A3A),
          ),
        ],
      ),
    ),
  );
}

class _Stat extends StatelessWidget {
  const _Stat({required this.value, required this.label, this.color});
  final int value;
  final String label;
  final Color? color;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: color ?? NusaColors.primary,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 8.5,
          ),
        ),
      ],
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
        style: const TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
      ),
    ],
  );
}

class _CaseCard extends StatelessWidget {
  const _CaseCard({required this.item, required this.onTap});
  final StudentCaseItem item;
  final ValueChanged<StudentCaseItem> onTap;

  @override
  Widget build(BuildContext context) {
    final color = studentCaseColor(item.status.color);
    return Card(
      child: InkWell(
        key: Key('student-case-${item.id}'),
        onTap: () => onTap(item),
        borderRadius: BorderRadius.circular(18),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Wrap(
                spacing: 7,
                runSpacing: 7,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  Text(
                    item.number,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: studentCaseSurface(item.status.color),
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      item.status.label,
                      style: TextStyle(
                        color: color,
                        fontSize: 9.5,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Text(
                item.title,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                item.status.description,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 11,
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  const Icon(
                    Icons.calendar_today_outlined,
                    size: 14,
                    color: NusaColors.textSecondary,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: Text(
                      studentCaseDate(item.incidentDate),
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 10,
                      ),
                    ),
                  ),
                  Text(
                    item.schoolClass?.name ?? '-',
                    style: const TextStyle(
                      color: NusaColors.primary,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Icon(
                    Icons.chevron_right_rounded,
                    size: 18,
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
}

class _UnlinkedAccount extends StatelessWidget {
  const _UnlinkedAccount();

  @override
  Widget build(BuildContext context) => const _EmptyMessage(
    icon: Icons.link_off_rounded,
    title: 'Akun belum terhubung ke data siswa',
    message: 'Hubungi administrator sekolah agar akun ini dihubungkan dengan data siswa yang benar.',
  );
}

class _EmptyCases extends StatelessWidget {
  const _EmptyCases();

  @override
  Widget build(BuildContext context) => const _EmptyMessage(
    icon: Icons.verified_user_outlined,
    title: 'Tidak ada kasus yang tercatat',
    message: 'Data akan tampil di sini apabila terdapat laporan yang berkaitan dengan Anda.',
  );
}

class _EmptyMessage extends StatelessWidget {
  const _EmptyMessage({
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
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          Icon(icon, size: 48, color: NusaColors.textSecondary),
          const SizedBox(height: 11),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 5),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
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
          const Icon(
            Icons.cloud_off_rounded,
            size: 54,
            color: NusaColors.textSecondary,
          ),
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
  _ => 'Progress kasus belum dapat dimuat.',
};
