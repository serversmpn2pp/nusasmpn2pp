import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/question_bank/application/question_bank_controller.dart';
import 'package:nusa/features/question_bank/domain/question_bank.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class QuestionBankListView extends ConsumerStatefulWidget {
  const QuestionBankListView({super.key});

  @override
  ConsumerState<QuestionBankListView> createState() =>
      _QuestionBankListViewState();
}

class _QuestionBankListViewState extends ConsumerState<QuestionBankListView> {
  final _search = TextEditingController();
  bool _loadingMore = false;

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(questionBankControllerProvider);
    final current = state.value;
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Bank Soal'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: state.isLoading
                ? null
                : ref.read(questionBankControllerProvider.notifier).refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      floatingActionButton: current?.access.canManage == true
          ? FloatingActionButton.extended(
              key: const Key('question-bank-add'),
              onPressed: () => context.push('/bank-soal/tambah'),
              icon: const Icon(Icons.add_rounded),
              label: const Text('Tambah Soal'),
            )
          : null,
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            if (current != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                child: Column(
                  children: [
                    _SummaryCard(summary: current.summary),
                    const SizedBox(height: 9),
                    NusaTextField(
                      fieldKey: const Key('question-bank-search'),
                      controller: _search,
                      hintText: 'Cari materi atau isi pertanyaan',
                      prefixIcon: Icons.search_rounded,
                      enabled: !state.isLoading,
                      onChanged: ref
                          .read(questionBankControllerProvider.notifier)
                          .search,
                    ),
                    const SizedBox(height: 8),
                    NusaDropdownField<String?>(
                      fieldKey: const Key('question-bank-context-filter'),
                      value: _contextKey(current.filter),
                      enabled: !state.isLoading,
                      decoration: const InputDecoration(
                        labelText: 'Bank mata pelajaran',
                        prefixIcon: Icon(Icons.menu_book_rounded),
                      ),
                      options: [
                        const NusaDropdownOption(
                          value: null,
                          label: 'Semua mata pelajaran dan tingkat',
                        ),
                        for (final item in current.references.contexts)
                          NusaDropdownOption(
                            value: item.key,
                            label: item.label,
                          ),
                      ],
                      onChanged: ref
                          .read(questionBankControllerProvider.notifier)
                          .filterContext,
                    ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('question-bank-type-filter'),
                            value: current.filter.type,
                            enabled: !state.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Jenis soal',
                            ),
                            options: [
                              const NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua jenis',
                              ),
                              for (final item in current.references.types)
                                NusaDropdownOption(
                                  value: item.code,
                                  label: item.label,
                                ),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      questionBankControllerProvider.notifier,
                                    )
                                    .filterType(value);
                              }
                            },
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('question-bank-status-filter'),
                            value: current.filter.status,
                            enabled: !state.isLoading,
                            decoration: const InputDecoration(
                              labelText: 'Status',
                            ),
                            options: [
                              const NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua status',
                              ),
                              for (final item in current.references.statuses)
                                NusaDropdownOption(
                                  value: item.code,
                                  label: item.label,
                                ),
                            ],
                            onChanged: (value) {
                              if (value != null) {
                                ref
                                    .read(
                                      questionBankControllerProvider.notifier,
                                    )
                                    .filterStatus(value);
                              }
                            },
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
                    : _QuestionResults(
                        page: current,
                        loadingMore: _loadingMore,
                        onRefresh: _refresh,
                        onLoadMore: _loadMore,
                      ),
                error: (error, stackTrace) =>
                    _ErrorState(message: _message(error), onRetry: _refresh),
                data: (page) => _QuestionResults(
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
      ref.read(questionBankControllerProvider.notifier).refresh();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(questionBankControllerProvider.notifier).loadMore();
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

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.summary});
  final QuestionBankSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(18),
    ),
    child: Row(
      children: [
        _SummaryValue(label: 'Total', value: summary.total),
        _SummaryValue(label: 'Siap', value: summary.ready, success: true),
        _SummaryValue(label: 'Draf', value: summary.draft),
        _SummaryValue(label: 'Arsip', value: summary.archived),
      ],
    ),
  );
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({
    required this.label,
    required this.value,
    this.success = false,
  });
  final String label;
  final int value;
  final bool success;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: TextStyle(
            color: success ? NusaColors.accent : Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w900,
          ),
        ),
        Text(label, style: const TextStyle(color: Colors.white70, fontSize: 9)),
      ],
    ),
  );
}

class _QuestionResults extends StatelessWidget {
  const _QuestionResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
  });
  final QuestionBankPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onLoadMore;

  @override
  Widget build(BuildContext context) {
    if (page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(24),
          children: const [
            SizedBox(height: 60),
            Icon(
              Icons.quiz_outlined,
              size: 52,
              color: NusaColors.textSecondary,
            ),
            SizedBox(height: 12),
            Text(
              'Belum ada soal CBT',
              textAlign: TextAlign.center,
              style: TextStyle(fontWeight: FontWeight.w800),
            ),
            SizedBox(height: 4),
            Text(
              'Ubah filter atau tambahkan soal baru.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: const PageStorageKey<String>('question-bank-results'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 5, 16, 100),
        itemCount: page.items.length + 1,
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == page.items.length) {
            if (!page.pagination.hasNextPage) {
              return Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  '${page.pagination.total} soal ditampilkan',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              );
            }
            return Center(
              child: TextButton.icon(
                onPressed: loadingMore ? null : onLoadMore,
                icon: loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: const Text('Muat Berikutnya'),
              ),
            );
          }
          return _QuestionCard(question: page.items[index]);
        },
      ),
    );
  }
}

class _QuestionCard extends StatelessWidget {
  const _QuestionCard({required this.question});
  final BankQuestion question;

  @override
  Widget build(BuildContext context) {
    final color = _statusColor(question.status);
    return Card(
      child: InkWell(
        key: Key('question-bank-item-${question.id}'),
        borderRadius: BorderRadius.circular(18),
        onTap: () => context.push('/bank-soal/${question.id}'),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 38,
                    height: 38,
                    decoration: BoxDecoration(
                      color: NusaColors.surfaceBlue,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.quiz_rounded,
                      color: NusaColors.primary,
                      size: 21,
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          question.code,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: NusaColors.primary,
                            fontSize: 11.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          '${question.subject?.name ?? '-'} · Kelas ${question.grade}',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: NusaColors.textSecondary,
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  _Pill(label: question.statusLabel, color: color),
                ],
              ),
              const SizedBox(height: 11),
              Text(
                question.question,
                maxLines: 4,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 13, height: 1.42),
              ),
              const SizedBox(height: 11),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  _Pill(label: question.typeLabel, color: NusaColors.primary),
                  _Pill(
                    label: question.categoryLabel,
                    color: const Color(0xFF7A56B3),
                  ),
                  _Pill(
                    label: '${_number(question.maximumScore)} poin',
                    color: const Color(0xFFB57900),
                  ),
                  if (question.usageCount > 0)
                    _Pill(
                      label: '${question.usageCount} pemakaian',
                      color: NusaColors.success,
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

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(
      color: color.withValues(alpha: 0.09),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      label,
      style: TextStyle(color: color, fontSize: 9, fontWeight: FontWeight.w700),
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
          const Icon(Icons.cloud_off_rounded, size: 48),
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

String? _contextKey(QuestionBankFilter filter) {
  if (filter.subjectId == null || filter.grade == 'semua') return null;
  return '${filter.subjectId}-${filter.grade}';
}

String _number(double value) =>
    value == value.roundToDouble() ? '${value.toInt()}' : '$value';

Color _statusColor(String status) => switch (status) {
  'siap' => NusaColors.success,
  'arsip' => NusaColors.textSecondary,
  _ => const Color(0xFFB57900),
};

String _message(Object error) => error is AppException
    ? error.message
    : 'Bank soal belum dapat dimuat. Silakan coba lagi.';
