import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/identity_photo/application/identity_photo_controller.dart';
import 'package:nusa/features/identity_photo/data/identity_photo_picker.dart';
import 'package:nusa/features/identity_photo/domain/identity_photo.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class IdentityPhotoView extends ConsumerStatefulWidget {
  const IdentityPhotoView({super.key});

  @override
  ConsumerState<IdentityPhotoView> createState() => _IdentityPhotoViewState();
}

class _IdentityPhotoViewState extends ConsumerState<IdentityPhotoView> {
  final _searchController = TextEditingController();
  Timer? _debounce;
  bool _loadingMore = false;
  String? _uploadingKey;

  @override
  void dispose() {
    _debounce?.cancel();
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final photos = ref.watch(identityPhotoControllerProvider);

    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Foto Identitas'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: photos.isLoading || _uploadingKey != null
                ? null
                : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: photos.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _PhotoError(
            message: _errorMessage(error),
            onRetry: _refresh,
          ),
          data: (page) => RefreshIndicator(
            onRefresh: _refresh,
            child: CustomScrollView(
              key: const PageStorageKey<String>('identity-photo-page'),
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(child: _buildFilters(page)),
                if (page.items.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: _EmptyPhotos(),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 2, 16, 12),
                    sliver: SliverList.separated(
                      itemCount: page.items.length,
                      separatorBuilder: (context, index) =>
                          const SizedBox(height: 9),
                      itemBuilder: (context, index) {
                        final person = page.items[index];
                        final key = '${page.tab}-${person.id}';
                        return _IdentityPhotoCard(
                          person: person,
                          tab: page.tab,
                          uploading: _uploadingKey == key,
                          enabled: _uploadingKey == null,
                          onUpload: () => _pickAndUpload(page.tab, person),
                        );
                      },
                    ),
                  ),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(16, 2, 16, 28),
                    child: page.pagination.hasNextPage
                        ? OutlinedButton.icon(
                            onPressed: _loadingMore ? null : _loadMore,
                            icon: _loadingMore
                                ? const SizedBox.square(
                                    dimension: 16,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.expand_more_rounded),
                            label: Text(
                              _loadingMore ? 'Memuat...' : 'Muat lebih banyak',
                            ),
                          )
                        : Text(
                            '${page.pagination.total} orang ditampilkan',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: NusaColors.textSecondary,
                              fontSize: 11,
                            ),
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

  Widget _buildFilters(IdentityPhotoPage page) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 10),
    child: Column(
      children: [
        _PhotoSummary(summary: page.summary),
        if (page.canManageStudents && page.canManageEmployees) ...[
          const SizedBox(height: 9),
          Row(
            children: [
              Expanded(
                child: FilterChip(
                  key: const Key('identity-photo-student-tab'),
                  label: const SizedBox(
                    width: double.infinity,
                    child: Text('Siswa', textAlign: TextAlign.center),
                  ),
                  avatar: const Icon(Icons.school_outlined, size: 17),
                  selected: page.tab == 'siswa',
                  showCheckmark: false,
                  onSelected: (_) => _selectTab('siswa'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilterChip(
                  key: const Key('identity-photo-employee-tab'),
                  label: const SizedBox(
                    width: double.infinity,
                    child: Text('Pegawai', textAlign: TextAlign.center),
                  ),
                  avatar: const Icon(Icons.badge_outlined, size: 17),
                  selected: page.tab == 'pegawai',
                  showCheckmark: false,
                  onSelected: (_) => _selectTab('pegawai'),
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: 9),
        if (page.tab == 'siswa') ...[
          NusaDropdownField<int>(
            fieldKey: const Key('identity-photo-academic-year'),
            value: page.academicYearId,
            options: page.academicYears
                .map(
                  (item) => NusaDropdownOption(
                    value: item.id,
                    label: '${item.name}${item.active ? ' · Aktif' : ''}',
                  ),
                )
                .toList(growable: false),
            decoration: const InputDecoration(
              labelText: 'Tahun pelajaran',
              prefixIcon: Icon(Icons.event_note_outlined),
            ),
            enabled: _uploadingKey == null,
            onChanged: _selectAcademicYear,
          ),
          const SizedBox(height: 8),
          NusaDropdownField<int>(
            fieldKey: const Key('identity-photo-class'),
            value: page.classId,
            options: page.classes
                .map(
                  (item) => NusaDropdownOption(
                    value: item.id,
                    label: item.name,
                  ),
                )
                .toList(growable: false),
            decoration: const InputDecoration(
              labelText: 'Kelas',
              prefixIcon: Icon(Icons.meeting_room_outlined),
            ),
            enabled: _uploadingKey == null && page.classes.isNotEmpty,
            onChanged: _selectClass,
          ),
        ] else ...[
          Row(
            children: [
              Expanded(
                child: NusaDropdownField<String>(
                  fieldKey: const Key('identity-photo-employee-type'),
                  value: page.employeeType,
                  options: [
                    const NusaDropdownOption(
                      value: '',
                      label: 'Semua jenis',
                    ),
                    ...page.employeeTypes.map(
                      (item) => NusaDropdownOption(value: item, label: item),
                    ),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Jenis pegawai',
                    prefixIcon: Icon(Icons.work_outline_rounded),
                  ),
                  enabled: _uploadingKey == null,
                  onChanged: (value) {
                    if (value != null) {
                      ref
                          .read(identityPhotoControllerProvider.notifier)
                          .filterEmployeeType(value);
                    }
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: NusaDropdownField<String>(
                  fieldKey: const Key('identity-photo-employee-status'),
                  value: page.employeeStatus,
                  options: const [
                    NusaDropdownOption(value: 'aktif', label: 'Aktif'),
                    NusaDropdownOption(value: 'nonaktif', label: 'Nonaktif'),
                    NusaDropdownOption(value: 'semua', label: 'Semua status'),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'Status pegawai',
                    prefixIcon: Icon(Icons.toggle_on_outlined),
                  ),
                  enabled: _uploadingKey == null,
                  onChanged: (value) {
                    if (value != null) {
                      ref
                          .read(identityPhotoControllerProvider.notifier)
                          .filterEmployeeStatus(value);
                    }
                  },
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: 8),
        NusaDropdownField<String>(
          fieldKey: const Key('identity-photo-status-filter'),
          value: page.photoStatus,
          options: const [
            NusaDropdownOption(value: 'semua', label: 'Semua status foto'),
            NusaDropdownOption(value: 'belum', label: 'Belum ada foto'),
            NusaDropdownOption(value: 'sudah', label: 'Sudah ada foto'),
          ],
          decoration: const InputDecoration(
            labelText: 'Status foto',
            prefixIcon: Icon(Icons.photo_camera_outlined),
          ),
          enabled: _uploadingKey == null,
          onChanged: (value) {
            if (value != null) {
              ref
                  .read(identityPhotoControllerProvider.notifier)
                  .filterPhotoStatus(value);
            }
          },
        ),
        const SizedBox(height: 8),
        NusaTextField(
          fieldKey: const Key('identity-photo-search'),
          controller: _searchController,
          hintText: page.tab == 'siswa'
              ? 'Cari nama, NIS, atau NISN'
              : 'Cari nama, NIP, atau NUPTK',
          prefixIcon: Icons.search_rounded,
          enabled: _uploadingKey == null,
          onChanged: _search,
          suffixIcon: _searchController.text.isEmpty
              ? null
              : IconButton(
                  onPressed: _clearSearch,
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
      ],
    ),
  );

  void _selectTab(String value) {
    _resetSearch();
    ref.read(identityPhotoControllerProvider.notifier).selectTab(value);
  }

  void _selectAcademicYear(int? value) {
    if (value == null) return;
    _resetSearch();
    ref
        .read(identityPhotoControllerProvider.notifier)
        .selectAcademicYear(value);
  }

  void _selectClass(int? value) {
    if (value == null) return;
    _resetSearch();
    ref.read(identityPhotoControllerProvider.notifier).selectClass(value);
  }

  void _resetSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 450), () {
      if (mounted) {
        ref.read(identityPhotoControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(identityPhotoControllerProvider.notifier).search('');
  }

  Future<void> _pickAndUpload(
    String tab,
    IdentityPhotoPerson person,
  ) async {
    final source = await showModalBottomSheet<IdentityPhotoSource>(
      context: context,
      useSafeArea: true,
      builder: (context) => _PhotoSourceSheet(person: person),
    );
    if (source == null || !mounted) return;

    try {
      final file = await ref.read(identityPhotoPickerProvider).pick(source);
      if (file == null || !mounted) return;
      final confirmed = await _confirmPhoto(person, file);
      if (!confirmed || !mounted) return;

      final key = '$tab-${person.id}';
      setState(() => _uploadingKey = key);
      await ref
          .read(identityPhotoActionsProvider)
          .upload(tab: tab, personId: person.id, file: file);
      await ref.read(identityPhotoControllerProvider.notifier).refresh();
      if (!mounted) return;
      _showMessage('Foto ${person.name} berhasil diperbarui.');
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _uploadingKey = null);
    }
  }

  Future<bool> _confirmPhoto(
    IdentityPhotoPerson person,
    IdentityPhotoPickedFile file,
  ) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Gunakan foto ini?'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(15),
                  child: SizedBox(
                    width: 150,
                    height: 190,
                    child: Image.memory(file.bytes, fit: BoxFit.cover),
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  person.name,
                  textAlign: TextAlign.center,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  '${_fileSize(file.bytes.length)} · ${file.name}',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: const Text('Pilih Ulang'),
              ),
              FilledButton.icon(
                key: const Key('confirm-identity-photo-upload'),
                onPressed: () => Navigator.pop(context, true),
                icon: const Icon(Icons.cloud_upload_outlined),
                label: const Text('Unggah Foto'),
              ),
            ],
          ),
        ) ??
        false;
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(identityPhotoControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _refresh() =>
      ref.read(identityPhotoControllerProvider.notifier).refresh();

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _PhotoSummary extends StatelessWidget {
  const _PhotoSummary({required this.summary});

  final IdentityPhotoSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryItem(label: 'Total', value: summary.total),
        _SummaryItem(label: 'Sudah', value: summary.withPhoto),
        _SummaryItem(label: 'Belum', value: summary.withoutPhoto),
      ],
    ),
  );
}

class _SummaryItem extends StatelessWidget {
  const _SummaryItem({required this.label, required this.value});

  final String label;
  final int value;

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(
      children: [
        Text(
          '$value',
          style: const TextStyle(
            color: Colors.white,
            fontSize: 20,
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          style: TextStyle(
            color: Colors.white.withValues(alpha: 0.72),
            fontSize: 10,
          ),
        ),
      ],
    ),
  );
}

class _IdentityPhotoCard extends StatelessWidget {
  const _IdentityPhotoCard({
    required this.person,
    required this.tab,
    required this.uploading,
    required this.enabled,
    required this.onUpload,
  });

  final IdentityPhotoPerson person;
  final String tab;
  final bool uploading;
  final bool enabled;
  final VoidCallback onUpload;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('identity-photo-$tab-${person.id}'),
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _PhotoPreview(person: person),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  person.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  person.identity,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 10.5,
                  ),
                ),
                if (person.detail.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    person.detail,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 10.5),
                  ),
                ],
                const SizedBox(height: 8),
                Row(
                  children: [
                    _PhotoStatus(hasPhoto: person.hasPhoto),
                    const Spacer(),
                    FilledButton.tonalIcon(
                      key: Key('upload-identity-photo-$tab-${person.id}'),
                      onPressed: enabled ? onUpload : null,
                      icon: uploading
                          ? const SizedBox.square(
                              dimension: 15,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Icon(
                              person.hasPhoto
                                  ? Icons.cameraswitch_outlined
                                  : Icons.add_a_photo_outlined,
                              size: 17,
                            ),
                      label: Text(
                        uploading
                            ? 'Mengunggah'
                            : person.hasPhoto
                            ? 'Ganti'
                            : 'Unggah',
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _PhotoPreview extends StatelessWidget {
  const _PhotoPreview({required this.person});

  final IdentityPhotoPerson person;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(12),
    child: Container(
      width: 72,
      height: 88,
      color: NusaColors.surfaceBlue,
      alignment: Alignment.center,
      child: person.photoUrl?.isNotEmpty == true
          ? Image.network(
              person.photoUrl!,
              width: 72,
              height: 88,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  _PhotoInitial(name: person.name),
            )
          : _PhotoInitial(name: person.name),
    ),
  );
}

class _PhotoInitial extends StatelessWidget {
  const _PhotoInitial({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      const Icon(Icons.person_outline_rounded, color: NusaColors.primary),
      const SizedBox(height: 3),
      Text(
        _initials(name),
        style: const TextStyle(
          color: NusaColors.primary,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _PhotoStatus extends StatelessWidget {
  const _PhotoStatus({required this.hasPhoto});

  final bool hasPhoto;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: (hasPhoto ? NusaColors.success : NusaColors.accent).withValues(
        alpha: 0.11,
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(
      hasPhoto ? 'Sudah ada foto' : 'Belum ada foto',
      style: TextStyle(
        color: hasPhoto ? NusaColors.success : NusaColors.textPrimary,
        fontSize: 9,
        fontWeight: FontWeight.w800,
      ),
    ),
  );
}

class _PhotoSourceSheet extends StatelessWidget {
  const _PhotoSourceSheet({required this.person});

  final IdentityPhotoPerson person;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Center(
          child: Container(
            width: 42,
            height: 4,
            decoration: BoxDecoration(
              color: NusaColors.outline,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          person.hasPhoto ? 'Ganti Foto' : 'Unggah Foto',
          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
        ),
        Text(
          person.name,
          style: const TextStyle(color: NusaColors.textSecondary, fontSize: 12),
        ),
        const SizedBox(height: 12),
        ListTile(
          key: const Key('identity-photo-source-camera'),
          leading: const CircleAvatar(
            backgroundColor: NusaColors.surfaceBlue,
            child: Icon(Icons.photo_camera_outlined, color: NusaColors.primary),
          ),
          title: const Text('Ambil dari kamera'),
          subtitle: const Text('Gunakan kamera perangkat sekarang'),
          onTap: () => Navigator.pop(context, IdentityPhotoSource.camera),
        ),
        ListTile(
          key: const Key('identity-photo-source-gallery'),
          leading: const CircleAvatar(
            backgroundColor: NusaColors.surfaceBlue,
            child: Icon(Icons.photo_library_outlined, color: NusaColors.primary),
          ),
          title: const Text('Pilih dari galeri'),
          subtitle: const Text('Pilih foto yang sudah tersimpan'),
          onTap: () => Navigator.pop(context, IdentityPhotoSource.gallery),
        ),
        const SizedBox(height: 4),
        const Text(
          'Foto otomatis diperkecil hingga 1200 × 1600. Ukuran akhir maksimal 1,5 MB.',
          style: TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
        ),
      ],
    ),
  );
}

class _EmptyPhotos extends StatelessWidget {
  const _EmptyPhotos();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(36),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.no_photography_outlined, size: 50, color: NusaColors.primary),
          SizedBox(height: 12),
          Text(
            'Tidak ada data yang cocok dengan filter foto ini.',
            textAlign: TextAlign.center,
            style: TextStyle(color: NusaColors.textSecondary),
          ),
        ],
      ),
    ),
  );
}

class _PhotoError extends StatelessWidget {
  const _PhotoError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 48, color: NusaColors.primary),
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

String _initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty);
  return parts.take(2).map((part) => part[0].toUpperCase()).join();
}

String _fileSize(int bytes) => bytes >= 1024 * 1024
    ? '${(bytes / 1024 / 1024).toStringAsFixed(1)} MB'
    : '${(bytes / 1024).ceil()} KB';

String _errorMessage(Object error) {
  if (error is ValidationException && error.errors.isNotEmpty) {
    final messages = error.errors.values.expand((items) => items).toList();
    if (messages.isNotEmpty) return messages.first;
  }
  return error is AppException
      ? error.message
      : 'Foto identitas belum dapat diproses.';
}
