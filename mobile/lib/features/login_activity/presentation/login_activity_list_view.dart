import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/login_activity/application/login_activity_controller.dart';
import 'package:nusa/features/login_activity/domain/login_activity.dart';
import 'package:nusa/features/login_activity/presentation/widgets/login_activity_components.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class LoginActivityListView extends ConsumerStatefulWidget {
  const LoginActivityListView({super.key});

  @override
  ConsumerState<LoginActivityListView> createState() =>
      _LoginActivityListViewState();
}

class _LoginActivityListViewState extends ConsumerState<LoginActivityListView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  String _view = 'pengguna';
  bool _loadingMore = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final activities = ref.watch(loginActivityControllerProvider);
    final current = activities.value;
    final filter = current?.filter;

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Aktivitas Login'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: activities.isLoading
                ? null
                : () => ref
                      .read(loginActivityControllerProvider.notifier)
                      .refresh(),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 8),
              child: Column(
                children: [
                  if (current != null) ...[
                    LoginActivitySummaryStrip(summary: current.summary),
                    const SizedBox(height: 10),
                  ],
                  SizedBox(
                    width: double.infinity,
                    child: SegmentedButton<String>(
                      key: const Key('login-activity-view'),
                      segments: const [
                        ButtonSegment(
                          value: 'pengguna',
                          label: Text('Daftar Pengguna'),
                          icon: Icon(Icons.people_alt_outlined),
                        ),
                        ButtonSegment(
                          value: 'riwayat',
                          label: Text('Riwayat'),
                          icon: Icon(Icons.history_rounded),
                        ),
                      ],
                      selected: {_view},
                      showSelectedIcon: false,
                      onSelectionChanged: activities.isLoading
                          ? null
                          : (selection) => _changeView(selection.first),
                    ),
                  ),
                  const SizedBox(height: 9),
                  NusaTextField(
                    fieldKey: const Key('login-activity-search'),
                    controller: _searchController,
                    hintText: 'Cari nama atau username',
                    prefixIcon: Icons.search_rounded,
                    textInputAction: TextInputAction.search,
                    onChanged: _search,
                    onFieldSubmitted: (value) {
                      _debounce?.cancel();
                      ref
                          .read(loginActivityControllerProvider.notifier)
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
                                    loginActivityControllerProvider.notifier,
                                  )
                                  .search('');
                            },
                            icon: const Icon(Icons.close_rounded),
                          ),
                  ),
                  const SizedBox(height: 9),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: NusaDropdownField<String>(
                          fieldKey: const Key('login-account-type-filter'),
                          value: filter?.accountType ?? 'semua',
                          decoration: const InputDecoration(
                            labelText: 'Jenis akun',
                          ),
                          options: const [
                            NusaDropdownOption(
                              value: 'semua',
                              label: 'Semua jenis akun',
                            ),
                            NusaDropdownOption(
                              value: 'administrator',
                              label: 'Administrator sistem',
                            ),
                            NusaDropdownOption(
                              value: 'pegawai',
                              label: 'Pegawai',
                            ),
                            NusaDropdownOption(value: 'siswa', label: 'Siswa'),
                            NusaDropdownOption(
                              value: 'orang_tua',
                              label: 'Orang tua',
                            ),
                          ],
                          onChanged: activities.isLoading
                              ? null
                              : (value) {
                                  if (value != null) {
                                    ref
                                        .read(
                                          loginActivityControllerProvider
                                              .notifier,
                                        )
                                        .filterAccountType(value);
                                  }
                                },
                        ),
                      ),
                      const SizedBox(width: 9),
                      Expanded(
                        child: _view == 'pengguna'
                            ? _LoginStatusFilter(
                                value: filter?.loginStatus ?? 'semua',
                                enabled: !activities.isLoading,
                                onChanged: (value) => ref
                                    .read(
                                      loginActivityControllerProvider.notifier,
                                    )
                                    .filterLoginStatus(value),
                              )
                            : _AttemptStatusFilter(
                                value: filter?.attemptStatus ?? 'semua',
                                enabled: !activities.isLoading,
                                onChanged: (value) => ref
                                    .read(
                                      loginActivityControllerProvider.notifier,
                                    )
                                    .filterAttemptStatus(value),
                              ),
                      ),
                    ],
                  ),
                  if (_view == 'riwayat') ...[
                    const SizedBox(height: 9),
                    Row(
                      children: [
                        Expanded(
                          child: NusaDropdownField<String>(
                            fieldKey: const Key('login-device-filter'),
                            value: filter?.device ?? 'semua',
                            decoration: const InputDecoration(
                              labelText: 'Perangkat',
                            ),
                            options: const [
                              NusaDropdownOption(
                                value: 'semua',
                                label: 'Semua perangkat',
                              ),
                              NusaDropdownOption(
                                value: 'android',
                                label: 'Android',
                              ),
                              NusaDropdownOption(
                                value: 'ios',
                                label: 'iPhone / iPad',
                              ),
                              NusaDropdownOption(
                                value: 'windows',
                                label: 'Windows',
                              ),
                              NusaDropdownOption(value: 'mac', label: 'Mac'),
                              NusaDropdownOption(
                                value: 'linux',
                                label: 'Linux',
                              ),
                              NusaDropdownOption(
                                value: 'lainnya',
                                label: 'Perangkat lainnya',
                              ),
                            ],
                            onChanged: activities.isLoading
                                ? null
                                : (value) {
                                    if (value != null) {
                                      ref
                                          .read(
                                            loginActivityControllerProvider
                                                .notifier,
                                          )
                                          .filterDevice(value);
                                    }
                                  },
                          ),
                        ),
                        const SizedBox(width: 9),
                        Expanded(
                          child: _DateRangeButton(
                            filter: filter,
                            enabled: !activities.isLoading,
                            onSelect: _selectDateRange,
                            onClear: _clearDateRange,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
            Expanded(
              child: activities.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, stackTrace) => _ActivityListError(
                  message: _errorMessage(error),
                  onRetry: () => ref
                      .read(loginActivityControllerProvider.notifier)
                      .refresh(),
                ),
                data: (page) => _ActivityResults(
                  page: page,
                  loadingMore: _loadingMore,
                  onRefresh: ref
                      .read(loginActivityControllerProvider.notifier)
                      .refresh,
                  onLoadMore: _loadMore,
                  onViewHistory: _viewHistory,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      ref.read(loginActivityControllerProvider.notifier).search(value);
    });
  }

  Future<void> _changeView(String value) async {
    setState(() => _view = value);
    await ref.read(loginActivityControllerProvider.notifier).changeView(value);
  }

  Future<void> _viewHistory(String username) async {
    _searchController.text = username;
    setState(() => _view = 'riwayat');
    await ref
        .read(loginActivityControllerProvider.notifier)
        .viewHistoryFor(username);
  }

  Future<void> _selectDateRange() async {
    final current = ref.read(loginActivityControllerProvider).value?.filter;
    final initialStart = DateTime.tryParse(current?.startDate ?? '');
    final initialEnd = DateTime.tryParse(current?.endDate ?? '');
    final now = DateTime.now();
    final selected = await showDateRangePicker(
      context: context,
      firstDate: DateTime(now.year - 5),
      lastDate: DateTime(now.year + 1, 12, 31),
      initialDateRange: initialStart != null && initialEnd != null
          ? DateTimeRange(start: initialStart, end: initialEnd)
          : null,
      helpText: 'Pilih periode aktivitas login',
      saveText: 'Terapkan',
    );
    if (selected == null) return;
    await ref
        .read(loginActivityControllerProvider.notifier)
        .filterDates(
          startDate: _dateValue(selected.start),
          endDate: _dateValue(selected.end),
        );
  }

  Future<void> _clearDateRange() =>
      ref.read(loginActivityControllerProvider.notifier).filterDates();

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(loginActivityControllerProvider.notifier).loadMore();
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

class _LoginStatusFilter extends StatelessWidget {
  const _LoginStatusFilter({
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  final String value;
  final bool enabled;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => NusaDropdownField<String>(
    fieldKey: const Key('login-status-filter'),
    value: value,
    decoration: const InputDecoration(labelText: 'Penggunaan'),
    options: const [
      NusaDropdownOption(value: 'semua', label: 'Semua akun'),
      NusaDropdownOption(value: 'pernah', label: 'Pernah login'),
      NusaDropdownOption(value: 'belum', label: 'Belum pernah login'),
    ],
    onChanged: enabled
        ? (value) {
            if (value != null) onChanged(value);
          }
        : null,
  );
}

class _AttemptStatusFilter extends StatelessWidget {
  const _AttemptStatusFilter({
    required this.value,
    required this.enabled,
    required this.onChanged,
  });

  final String value;
  final bool enabled;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => NusaDropdownField<String>(
    fieldKey: const Key('login-attempt-status-filter'),
    value: value,
    decoration: const InputDecoration(labelText: 'Hasil'),
    options: const [
      NusaDropdownOption(value: 'semua', label: 'Semua hasil'),
      NusaDropdownOption(value: 'berhasil', label: 'Berhasil'),
      NusaDropdownOption(value: 'gagal', label: 'Gagal'),
    ],
    onChanged: enabled
        ? (value) {
            if (value != null) onChanged(value);
          }
        : null,
  );
}

class _DateRangeButton extends StatelessWidget {
  const _DateRangeButton({
    required this.filter,
    required this.enabled,
    required this.onSelect,
    required this.onClear,
  });

  final LoginActivityFilter? filter;
  final bool enabled;
  final VoidCallback onSelect;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final hasRange = filter?.startDate != null || filter?.endDate != null;
    return SizedBox(
      height: 56,
      child: OutlinedButton.icon(
        key: const Key('login-date-filter'),
        onPressed: enabled ? onSelect : null,
        onLongPress: enabled && hasRange ? onClear : null,
        icon: const Icon(Icons.date_range_rounded, size: 19),
        label: Text(
          hasRange
              ? '${_shortDate(filter?.startDate)} – ${_shortDate(filter?.endDate)}'
              : 'Semua tanggal',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      ),
    );
  }
}

class _ActivityResults extends StatelessWidget {
  const _ActivityResults({
    required this.page,
    required this.loadingMore,
    required this.onRefresh,
    required this.onLoadMore,
    required this.onViewHistory,
  });

  final LoginActivityPage page;
  final bool loadingMore;
  final Future<void> Function() onRefresh;
  final VoidCallback onLoadMore;
  final ValueChanged<String> onViewHistory;

  @override
  Widget build(BuildContext context) {
    final count = page.filter.view == 'pengguna'
        ? page.users.length
        : page.attempts.length;
    if (count == 0) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 55),
            Icon(
              page.filter.view == 'pengguna'
                  ? Icons.person_search_rounded
                  : Icons.history_toggle_off_rounded,
              size: 52,
              color: NusaColors.primary,
            ),
            const SizedBox(height: 12),
            Text(
              page.filter.view == 'pengguna'
                  ? 'Tidak ada pengguna yang sesuai filter.'
                  : 'Tidak ada riwayat login yang sesuai filter.',
              textAlign: TextAlign.center,
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView.separated(
        key: PageStorageKey<String>('login-activity-${page.filter.view}'),
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 30),
        itemCount: count + (page.pagination.hasNextPage ? 1 : 0),
        separatorBuilder: (context, index) => const SizedBox(height: 9),
        itemBuilder: (context, index) {
          if (index == count) {
            return Center(
              child: OutlinedButton.icon(
                onPressed: loadingMore ? null : onLoadMore,
                icon: loadingMore
                    ? const SizedBox.square(
                        dimension: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.expand_more_rounded),
                label: const Text('Muat berikutnya'),
              ),
            );
          }

          if (page.filter.view == 'pengguna') {
            final user = page.users[index];
            return LoginUserCard(
              user: user,
              onTap: () => onViewHistory(user.username),
            );
          }

          final attempt = page.attempts[index];
          return LoginAttemptCard(
            attempt: attempt,
            onTap: () => context.push('/aktivitas-login/${attempt.id}'),
          );
        },
      ),
    );
  }
}

class _ActivityListError extends StatelessWidget {
  const _ActivityListError({required this.message, required this.onRetry});

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
            Icons.security_rounded,
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

String _dateValue(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

String _shortDate(String? value) {
  final date = DateTime.tryParse(value ?? '');
  if (date == null) return '-';
  return '${date.day.toString().padLeft(2, '0')}/'
      '${date.month.toString().padLeft(2, '0')}/${date.year}';
}

String _errorMessage(Object error) => error is AppException
    ? error.message
    : 'Aktivitas login belum dapat dimuat. Silakan coba lagi.';
