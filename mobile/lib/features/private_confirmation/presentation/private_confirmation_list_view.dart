import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/private_confirmation/application/private_confirmation_controller.dart';
import 'package:nusa/features/private_confirmation/domain/private_confirmation.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class PrivateConfirmationListView extends ConsumerStatefulWidget {
  const PrivateConfirmationListView({super.key});

  @override
  ConsumerState<PrivateConfirmationListView> createState() =>
      _PrivateConfirmationListViewState();
}

class _PrivateConfirmationListViewState
    extends ConsumerState<PrivateConfirmationListView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(privateConfirmationControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Konfirmasi Privat'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: data.isLoading
                ? null
                : () => ref
                      .read(privateConfirmationControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: data.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _ErrorState(
            message: _errorMessage(error),
            onRetry: () => ref
                .read(privateConfirmationControllerProvider.notifier)
                .refresh(),
          ),
          data: (page) => RefreshIndicator(
            onRefresh: ref
                .read(privateConfirmationControllerProvider.notifier)
                .refresh,
            child: ListView(
              key: const PageStorageKey<String>('private-confirmation-list'),
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 30),
              children: [
                _PrivateHeader(page: page),
                const SizedBox(height: 12),
                _SummaryStrip(summary: page.summary),
                const SizedBox(height: 12),
                NusaTextField(
                  fieldKey: const Key('private-confirmation-search'),
                  controller: _searchController,
                  hintText: 'Cari nama siswi atau NISN',
                  prefixIcon: Icons.search_rounded,
                  textInputAction: TextInputAction.search,
                  onChanged: _search,
                  onFieldSubmitted: (value) {
                    _debounce?.cancel();
                    ref
                        .read(privateConfirmationControllerProvider.notifier)
                        .search(value);
                  },
                  suffixIcon: _searchController.text.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Hapus pencarian',
                          onPressed: () {
                            _searchController.clear();
                            setState(() {});
                            ref
                                .read(
                                  privateConfirmationControllerProvider
                                      .notifier,
                                )
                                .search('');
                          },
                          icon: const Icon(Icons.close_rounded),
                        ),
                ),
                const SizedBox(height: 10),
                NusaDropdownField<int?>(
                  fieldKey: const Key('private-confirmation-class-filter'),
                  value: page.filter.classId,
                  decoration: const InputDecoration(
                    labelText: 'Cakupan kelas',
                    prefixIcon: Icon(Icons.class_outlined),
                  ),
                  options: [
                    const NusaDropdownOption<int?>(
                      value: null,
                      label: 'Semua kelas yang ditugaskan',
                    ),
                    ...page.classes.map(
                      (item) => NusaDropdownOption<int?>(
                        value: item.id,
                        label: item.name,
                      ),
                    ),
                  ],
                  onChanged: (value) => ref
                      .read(privateConfirmationControllerProvider.notifier)
                      .selectClass(value),
                ),
                const SizedBox(height: 18),
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Perlu Dikonfirmasi',
                        style: TextStyle(
                          color: NusaColors.textPrimary,
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Text(
                      '${page.pagination.total} siswi',
                      style: const TextStyle(
                        color: NusaColors.textSecondary,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                if (page.items.isEmpty)
                  const _EmptyState()
                else
                  ...page.items.map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _ConfirmationCard(
                        item: item,
                        onTap: () => context.push(
                          '/konfirmasi-berhalangan-ibadah/${item.id}',
                        ),
                      ),
                    ),
                  ),
                if (page.pagination.hasNextPage) ...[
                  const SizedBox(height: 2),
                  OutlinedButton.icon(
                    key: const Key('private-confirmation-load-more'),
                    onPressed: _loadingMore ? null : _loadMore,
                    icon: _loadingMore
                        ? const SizedBox.square(
                            dimension: 17,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.expand_more_rounded),
                    label: Text(
                      _loadingMore ? 'Memuat...' : 'Muat antrean berikutnya',
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      ref.read(privateConfirmationControllerProvider.notifier).search(value);
    });
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(privateConfirmationControllerProvider.notifier).loadMore();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context)
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(_errorMessage(error))));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }
}

class _PrivateHeader extends StatelessWidget {
  const _PrivateHeader({required this.page});

  final PrivateConfirmationPage page;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(17),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primaryDark, NusaColors.primary],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.17),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.13),
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.privacy_tip_rounded,
                color: NusaColors.accent,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'RUANG PRIVAT PENDAMPING',
                    style: TextStyle(
                      color: NusaColors.accent,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'Tahun Pelajaran ${page.academicYear.name}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Text(
          page.privacyMessage,
          style: const TextStyle(
            color: Colors.white70,
            fontSize: 11.5,
            height: 1.45,
          ),
        ),
      ],
    ),
  );
}

class _SummaryStrip extends StatelessWidget {
  const _SummaryStrip({required this.summary});

  final PrivateConfirmationSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: Row(
      children: [
        _SummaryItem(
          value: summary.pending,
          label: 'Perlu\nkonfirmasi',
          color: const Color(0xFFB57900),
        ),
        _SummaryItem(
          value: summary.monitored,
          label: 'Sedang\ndipantau',
          color: NusaColors.primaryLight,
        ),
        _SummaryItem(
          value: summary.completedThisMonth,
          label: 'Selesai\nbulan ini',
          color: NusaColors.success,
        ),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({
    required this.value,
    required this.label,
    required this.color,
  });

  final int value;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: color,
            fontSize: 19,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 3),
        Text(
          label,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
            height: 1.2,
          ),
        ),
      ],
    ),
  );
}

class _ConfirmationCard extends StatelessWidget {
  const _ConfirmationCard({required this.item, required this.onTap});

  final PrivateConfirmationItem item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(17),
      side: const BorderSide(color: NusaColors.outline),
    ),
    child: InkWell(
      key: Key('private-confirmation-item-${item.id}'),
      onTap: onTap,
      borderRadius: BorderRadius.circular(17),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            CircleAvatar(
              radius: 23,
              backgroundColor: NusaColors.surfaceBlue,
              child: Text(
                item.student.initials,
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.student.name,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textPrimary,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    'NISN ${item.student.nisn} · ${item.schoolClass.name}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      _Tag(
                        icon: Icons.timelapse_rounded,
                        label: 'Hari ke-${item.dayNumber}',
                        color: const Color(0xFFB57900),
                      ),
                      _Tag(
                        icon: Icons.fact_check_outlined,
                        label: '${item.attendanceCount} catatan scan',
                        color: NusaColors.primary,
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Dimulai ${item.startDateLabel}',
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                ],
              ),
            ),
            const Padding(
              padding: EdgeInsets.only(top: 12),
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

class _Tag extends StatelessWidget {
  const _Tag({required this.icon, required this.label, required this.color});

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: color, size: 14),
        const SizedBox(width: 4),
        Text(
          label,
          style: TextStyle(
            color: color,
            fontSize: 10,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 30),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(17),
      border: Border.all(color: NusaColors.outline),
    ),
    child: const Column(
      children: [
        Icon(Icons.verified_user_rounded, color: NusaColors.success, size: 42),
        SizedBox(height: 10),
        Text(
          'Tidak ada antrean konfirmasi',
          textAlign: TextAlign.center,
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        SizedBox(height: 5),
        Text(
          'Semua periode dalam cakupan kelas Anda masih dipantau atau sudah selesai.',
          textAlign: TextAlign.center,
          style: TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 11.5,
            height: 1.4,
          ),
        ),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.privacy_tip_rounded,
            size: 50,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Data konfirmasi privat belum dapat dimuat. Silakan coba lagi.';
