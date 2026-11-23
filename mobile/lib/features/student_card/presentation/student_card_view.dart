import 'dart:async';
import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_card/application/student_card_controller.dart';
import 'package:nusa/features/student_card/data/student_card_file_saver.dart';
import 'package:nusa/features/student_card/data/student_card_pdf.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

import 'student_card_artwork.dart';

class StudentCardView extends ConsumerStatefulWidget {
  const StudentCardView({super.key});

  @override
  ConsumerState<StudentCardView> createState() => _StudentCardViewState();
}

class _StudentCardViewState extends ConsumerState<StudentCardView> {
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
    final cards = ref.watch(studentCardControllerProvider);
    return Scaffold(
      backgroundColor: NusaColors.background,
      appBar: AppBar(
        title: const Text('Kartu Pelajar'),
        actions: [
          IconButton(
            tooltip: 'Perbarui',
            onPressed: cards.isLoading ? null : _refresh,
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        top: false,
        child: cards.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (error, stackTrace) => _StudentCardError(
            message: _errorMessage(error),
            onRetry: _refresh,
          ),
          data: (page) => RefreshIndicator(
            onRefresh: _refresh,
            child: CustomScrollView(
              key: const PageStorageKey<String>('student-card-page'),
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(child: _buildFilters(page)),
                if (page.items.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: _EmptyStudentCards(),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                    sliver: SliverList.separated(
                      itemCount: page.items.length,
                      separatorBuilder: (context, index) =>
                          const SizedBox(height: 9),
                      itemBuilder: (context, index) {
                        final student = page.items[index];
                        return _StudentCardListItem(
                          student: student,
                          onTap: () => _openPreview(student, page),
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
                            '${page.pagination.total} kartu ditampilkan',
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

  Widget _buildFilters(StudentCardPage page) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
    child: Column(
      children: [
        _StudentCardSummary(summary: page.summary),
        const SizedBox(height: 10),
        NusaDropdownField<int>(
          fieldKey: const Key('student-card-academic-year-filter'),
          value: page.selectedAcademicYearId,
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
          onChanged: (value) {
            if (value != null) {
              _resetSearch();
              ref
                  .read(studentCardControllerProvider.notifier)
                  .selectAcademicYear(value);
            }
          },
        ),
        const SizedBox(height: 8),
        NusaDropdownField<int>(
          fieldKey: const Key('student-card-class-filter'),
          value: page.selectedClassId,
          options: page.classes
              .map(
                (item) => NusaDropdownOption(value: item.id, label: item.name),
              )
              .toList(growable: false),
          decoration: const InputDecoration(
            labelText: 'Kelas',
            prefixIcon: Icon(Icons.meeting_room_outlined),
          ),
          enabled: page.classes.isNotEmpty,
          onChanged: (value) {
            if (value != null) {
              _resetSearch();
              ref
                  .read(studentCardControllerProvider.notifier)
                  .selectClass(value);
            }
          },
        ),
        const SizedBox(height: 8),
        NusaTextField(
          fieldKey: const Key('student-card-search'),
          controller: _searchController,
          hintText: 'Cari nama, NIS, atau NISN',
          prefixIcon: Icons.search_rounded,
          onChanged: _search,
          suffixIcon: _searchController.text.isEmpty
              ? null
              : IconButton(
                  onPressed: _clearSearch,
                  icon: const Icon(Icons.close_rounded),
                ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            const Icon(
              Icons.straighten_rounded,
              size: 15,
              color: NusaColors.textSecondary,
            ),
            const SizedBox(width: 5),
            Expanded(
              child: Text(
                'Ukuran ${page.cardSize.widthMillimeter.toStringAsFixed(2)} × '
                '${page.cardSize.heightMillimeter.toStringAsFixed(2)} mm · portrait',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
            ),
            Icon(
              page.canPrint ? Icons.print_rounded : Icons.visibility_outlined,
              size: 15,
              color: NusaColors.primary,
            ),
            const SizedBox(width: 4),
            Text(
              page.canPrint ? 'Bisa disimpan' : 'Pratinjau saja',
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 10,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ],
    ),
  );

  void _resetSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
  }

  void _search(String value) {
    setState(() {});
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 700), () {
      if (mounted) {
        ref.read(studentCardControllerProvider.notifier).search(value);
      }
    });
  }

  void _clearSearch() {
    _debounce?.cancel();
    _searchController.clear();
    setState(() {});
    ref.read(studentCardControllerProvider.notifier).search('');
  }

  Future<void> _loadMore() async {
    if (_loadingMore) return;
    setState(() => _loadingMore = true);
    try {
      await ref.read(studentCardControllerProvider.notifier).loadMore();
    } catch (error) {
      if (mounted) _showMessage(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _loadingMore = false);
    }
  }

  Future<void> _refresh() =>
      ref.read(studentCardControllerProvider.notifier).refresh();

  Future<void> _openPreview(StudentCardPerson student, StudentCardPage page) =>
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (context) => StudentCardPreviewView(
            student: student,
            cardSize: page.cardSize,
            canPrint: page.canPrint,
          ),
        ),
      );

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class StudentCardPreviewView extends ConsumerStatefulWidget {
  const StudentCardPreviewView({
    required this.student,
    required this.cardSize,
    required this.canPrint,
    super.key,
  });

  final StudentCardPerson student;
  final StudentCardSize cardSize;
  final bool canPrint;

  @override
  ConsumerState<StudentCardPreviewView> createState() =>
      _StudentCardPreviewViewState();
}

enum _StudentCardFormat { pdf, frontPng, backPng }

class _StudentCardPreviewViewState
    extends ConsumerState<StudentCardPreviewView> {
  final _captureKeys = {
    for (final side in StudentCardSide.values) side: GlobalKey(),
  };
  StudentCardSide _side = StudentCardSide.front;
  ImageProvider _photo = StudentCardArtwork.defaultPhoto;
  bool _imagesRequested = false;
  bool _loadingImages = true;
  bool _saving = false;
  String? _imageError;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_imagesRequested) {
      _imagesRequested = true;
      unawaited(_loadImages());
    }
  }

  Future<void> _loadImages() async {
    final url = widget.student.photoUrl;
    final ImageProvider photo = url?.isNotEmpty == true
        ? NetworkImage(url!)
        : StudentCardArtwork.defaultPhoto;
    try {
      await Future.wait([
        _preload(StudentCardArtwork.frontBackground),
        _preload(StudentCardArtwork.backBackground),
        _preload(StudentCardArtwork.schoolLogo),
        _preload(StudentCardArtwork.nusaLogo),
        _preload(photo),
      ]);
      if (!mounted) return;
      setState(() {
        _photo = photo;
        _imageError = null;
        _loadingImages = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _imageError =
            'Gambar kartu belum termuat. Coba lagi sebelum mengunduh.';
        _loadingImages = false;
      });
    }
  }

  Future<void> _preload(ImageProvider image) async {
    var failed = false;
    await precacheImage(
      image,
      context,
      onError: (_, _) => failed = true,
    ).timeout(const Duration(seconds: 15));
    if (failed) throw StateError('Gambar kartu tidak tersedia.');
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: NusaColors.background,
    appBar: AppBar(title: const Text('Pratinjau Kartu')),
    body: SafeArea(
      top: false,
      bottom: !widget.canPrint,
      child: SingleChildScrollView(
        key: const PageStorageKey<String>('student-card-preview'),
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _PreviewHeader(student: widget.student),
            const SizedBox(height: 12),
            Center(
              child: SegmentedButton<StudentCardSide>(
                key: const Key('student-card-side-selector'),
                segments: const [
                  ButtonSegment(
                    value: StudentCardSide.front,
                    icon: Icon(Icons.school_outlined),
                    label: Text('Depan'),
                  ),
                  ButtonSegment(
                    value: StudentCardSide.back,
                    icon: Icon(Icons.qr_code_rounded),
                    label: Text('Belakang'),
                  ),
                ],
                selected: {_side},
                onSelectionChanged: _saving
                    ? null
                    : (selection) {
                        setState(() => _side = selection.first);
                      },
              ),
            ),
            const SizedBox(height: 14),
            Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 330),
                child: AspectRatio(
                  aspectRatio: widget.cardSize.aspectRatio,
                  child: FittedBox(
                    fit: BoxFit.contain,
                    child: SizedBox(
                      width:
                          widget.cardSize.widthMillimeter *
                          studentCardPixelsPerMm,
                      height:
                          widget.cardSize.heightMillimeter *
                          studentCardPixelsPerMm,
                      child: Stack(
                        fit: StackFit.expand,
                        children: [
                          // Paint both faces at the same scale for a two-sided export.
                          for (final side in [
                            ...StudentCardSide.values.where(
                              (side) => side != _side,
                            ),
                            _side,
                          ])
                            ExcludeSemantics(
                              key: ValueKey(side),
                              excluding: side != _side,
                              child: RepaintBoundary(
                                key: _captureKeys[side],
                                child: StudentCardArtwork(
                                  student: widget.student,
                                  side: side,
                                  photo: _photo,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 14),
            _ReadinessNote(student: widget.student),
            if (_imageError != null) ...[
              const SizedBox(height: 8),
              Text(_imageError!, textAlign: TextAlign.center),
              TextButton.icon(
                onPressed: _loadingImages
                    ? null
                    : () {
                        setState(() {
                          _loadingImages = true;
                          _imageError = null;
                        });
                        unawaited(_loadImages());
                      },
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Muat ulang gambar'),
              ),
            ],
            if (!widget.canPrint) ...[
              const SizedBox(height: 12),
              const _ViewOnlyNotice(),
            ],
          ],
        ),
      ),
    ),
    bottomNavigationBar: !widget.canPrint
        ? null
        : SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: FilledButton.icon(
                key: const Key('download-student-card'),
                onPressed: _saving || _loadingImages || _imageError != null
                    ? null
                    : _chooseFormat,
                icon: _saving || _loadingImages
                    ? const SizedBox.square(
                        dimension: 17,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.download_rounded),
                label: Text(
                  _saving
                      ? 'Menyimpan...'
                      : _loadingImages
                      ? 'Menyiapkan kartu...'
                      : 'Unduh Kartu',
                ),
              ),
            ),
          ),
  );

  Future<void> _chooseFormat() async {
    final format = await showModalBottomSheet<_StudentCardFormat>(
      context: context,
      useSafeArea: true,
      builder: (sheet) => SafeArea(
        top: false,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final (format, label, icon) in [
              (
                _StudentCardFormat.pdf,
                'PDF dua sisi',
                Icons.picture_as_pdf_outlined,
              ),
              (
                _StudentCardFormat.frontPng,
                'PNG sisi depan',
                Icons.image_outlined,
              ),
              (
                _StudentCardFormat.backPng,
                'PNG sisi belakang',
                Icons.qr_code_rounded,
              ),
            ])
              ListTile(
                leading: Icon(icon),
                title: Text(label),
                onTap: () => Navigator.pop(sheet, format),
              ),
          ],
        ),
      ),
    );
    if (mounted && format != null) await _save(format);
  }

  Future<void> _save(_StudentCardFormat format) async {
    if (_saving || _loadingImages || _imageError != null || !widget.canPrint) {
      return;
    }
    setState(() => _saving = true);
    try {
      final front = _captureKeys[StudentCardSide.front]!.currentContext!;
      await Scrollable.ensureVisible(front, alignment: .5);
      await WidgetsBinding.instance.endOfFrame;
      if (!mounted) return;

      final isPdf = format == _StudentCardFormat.pdf;
      final side = format == _StudentCardFormat.backPng
          ? StudentCardSide.back
          : StudentCardSide.front;
      final name = 'kartu-pelajar-${_slug(widget.student.name)}';
      final fileName = isPdf
          ? '$name.pdf'
          : '$name-${side == StudentCardSide.front ? 'depan' : 'belakang'}.png';
      final bytes = isPdf
          ? await buildStudentCardPdf(
              size: widget.cardSize,
              front: await _capture(StudentCardSide.front),
              back: await _capture(StudentCardSide.back),
            )
          : await _capture(side);
      if (!mounted) return;
      final saved = await ref
          .read(studentCardFileSaverProvider)
          .save(fileName: fileName, bytes: bytes);
      if (mounted && saved) _showMessage('Kartu pelajar berhasil disimpan.');
    } catch (_) {
      if (mounted) _showMessage('Kartu belum berhasil disimpan. Coba lagi.');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<Uint8List> _capture(StudentCardSide side) async {
    final boundary =
        _captureKeys[side]!.currentContext?.findRenderObject()
            as RenderRepaintBoundary?;
    if (boundary == null) throw StateError('Pratinjau kartu belum siap.');
    final image = await boundary.toImage(pixelRatio: 4);
    try {
      final data = await image.toByteData(format: ui.ImageByteFormat.png);
      if (data == null) throw StateError('Gambar kartu belum terbentuk.');
      return data.buffer.asUint8List(data.offsetInBytes, data.lengthInBytes);
    } finally {
      image.dispose();
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }
}

class _StudentCardSummary extends StatelessWidget {
  const _StudentCardSummary({required this.summary});

  final StudentCardSummary summary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 13),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(17),
    ),
    child: Row(
      children: [
        _SummaryValue(label: 'Total', value: summary.total),
        _SummaryValue(label: 'QR siap', value: summary.qrReady),
        _SummaryValue(label: 'Ada foto', value: summary.withPhoto),
      ],
    ),
  );
}

class _SummaryValue extends StatelessWidget {
  const _SummaryValue({required this.label, required this.value});

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

class _StudentCardListItem extends StatelessWidget {
  const _StudentCardListItem({required this.student, required this.onTap});

  final StudentCardPerson student;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    key: Key('student-card-${student.studentId}'),
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            _StudentAvatar(student: student),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    student.name,
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
                    student.nisnLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: NusaColors.textSecondary,
                      fontSize: 10.5,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    '${student.className}${student.rollNumber == null ? '' : ' · Absen ${student.rollNumber}'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 10.5),
                  ),
                  const SizedBox(height: 7),
                  Wrap(
                    spacing: 5,
                    runSpacing: 5,
                    children: [
                      _ReadyBadge(
                        ready: student.hasPhoto,
                        readyText: 'Foto siap',
                        missingText: 'Tanpa foto',
                        icon: Icons.portrait_rounded,
                      ),
                      _ReadyBadge(
                        ready: student.canMakeQr,
                        readyText: 'QR siap',
                        missingText: 'QR belum siap',
                        icon: Icons.qr_code_rounded,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            const Icon(Icons.chevron_right_rounded, color: NusaColors.primary),
          ],
        ),
      ),
    ),
  );
}

class _StudentAvatar extends StatelessWidget {
  const _StudentAvatar({required this.student});

  final StudentCardPerson student;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(13),
    child: Container(
      width: 64,
      height: 76,
      color: NusaColors.surfaceBlue,
      alignment: Alignment.center,
      child: student.photoUrl?.isNotEmpty == true
          ? Image.network(
              student.photoUrl!,
              width: 64,
              height: 76,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) =>
                  _AvatarInitial(initials: student.initials),
            )
          : _AvatarInitial(initials: student.initials),
    ),
  );
}

class _AvatarInitial extends StatelessWidget {
  const _AvatarInitial({required this.initials});

  final String initials;

  @override
  Widget build(BuildContext context) => Column(
    mainAxisSize: MainAxisSize.min,
    children: [
      const Icon(Icons.person_outline_rounded, color: NusaColors.primary),
      const SizedBox(height: 2),
      Text(
        initials.isEmpty ? 'SW' : initials,
        style: const TextStyle(
          color: NusaColors.primary,
          fontSize: 10,
          fontWeight: FontWeight.w800,
        ),
      ),
    ],
  );
}

class _ReadyBadge extends StatelessWidget {
  const _ReadyBadge({
    required this.ready,
    required this.readyText,
    required this.missingText,
    required this.icon,
  });

  final bool ready;
  final String readyText;
  final String missingText;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
    decoration: BoxDecoration(
      color: (ready ? NusaColors.success : NusaColors.accent).withValues(
        alpha: 0.11,
      ),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(
          icon,
          size: 11,
          color: ready ? NusaColors.success : NusaColors.textPrimary,
        ),
        const SizedBox(width: 3),
        Text(
          ready ? readyText : missingText,
          style: TextStyle(
            color: ready ? NusaColors.success : NusaColors.textPrimary,
            fontSize: 8.5,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _PreviewHeader extends StatelessWidget {
  const _PreviewHeader({required this.student});

  final StudentCardPerson student;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(13),
      child: Row(
        children: [
          _StudentAvatar(student: student),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  student.name,
                  style: const TextStyle(
                    color: NusaColors.textPrimary,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  student.nisnLabel,
                  style: const TextStyle(
                    color: NusaColors.textSecondary,
                    fontSize: 11,
                  ),
                ),
                Text(
                  '${student.className} · ${student.academicYear}',
                  style: const TextStyle(fontSize: 11),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _ReadinessNote extends StatelessWidget {
  const _ReadinessNote({required this.student});

  final StudentCardPerson student;

  @override
  Widget build(BuildContext context) {
    final ready = student.hasPhoto && student.canMakeQr;
    final message = switch ((student.hasPhoto, student.canMakeQr)) {
      (true, true) => 'Kartu siap digunakan dan QR presensi dapat dipindai.',
      (false, true) => 'QR sudah siap, tetapi foto identitas belum tersedia. Kartu memakai foto bawaan.',
      (true, false) => 'Foto sudah tersedia, tetapi QR belum dapat dibuat karena NISN bukan angka yang valid.',
      _ => 'Foto identitas dan NISN numerik perlu dilengkapi agar kartu siap digunakan.',
    };
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: (ready ? NusaColors.success : NusaColors.accent).withValues(
          alpha: 0.09,
        ),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: (ready ? NusaColors.success : NusaColors.accent).withValues(
            alpha: 0.35,
          ),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            ready ? Icons.verified_rounded : Icons.info_outline_rounded,
            color: ready ? NusaColors.success : NusaColors.textPrimary,
            size: 20,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(fontSize: 11.5, height: 1.35),
            ),
          ),
        ],
      ),
    );
  }
}

class _ViewOnlyNotice extends StatelessWidget {
  const _ViewOnlyNotice();

  @override
  Widget build(BuildContext context) => Container(
    key: const Key('student-card-view-only-notice'),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(14),
    ),
    child: const Row(
      children: [
        Icon(Icons.visibility_outlined, color: NusaColors.primary),
        SizedBox(width: 9),
        Expanded(
          child: Text(
            'Akun ini memiliki izin melihat kartu. Penyimpanan berkas memerlukan izin cetak kartu pelajar.',
            style: TextStyle(fontSize: 11.5, height: 1.35),
          ),
        ),
      ],
    ),
  );
}

class _EmptyStudentCards extends StatelessWidget {
  const _EmptyStudentCards();

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(
      padding: EdgeInsets.all(36),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.school_outlined, size: 52, color: NusaColors.primary),
          SizedBox(height: 12),
          Text(
            'Tidak ada kartu pelajar yang cocok dengan kelas dan pencarian ini.',
            textAlign: TextAlign.center,
            style: TextStyle(color: NusaColors.textSecondary),
          ),
        ],
      ),
    ),
  );
}

class _StudentCardError extends StatelessWidget {
  const _StudentCardError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(28),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 48,
            color: NusaColors.primary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 14),
          FilledButton.tonalIcon(
            style: const ButtonStyle(
              minimumSize: WidgetStatePropertyAll(Size(0, 44)),
            ),
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Coba lagi'),
          ),
        ],
      ),
    ),
  );
}

String _slug(String value) {
  final normalized = value
      .toLowerCase()
      .replaceAll(RegExp(r'[^a-z0-9]+'), '-')
      .replaceAll(RegExp(r'^-+|-+$'), '');
  return normalized.isEmpty ? 'siswa' : normalized;
}

String _errorMessage(Object error) =>
    error is AppException ? error.message : 'Kartu pelajar belum dapat dimuat.';
