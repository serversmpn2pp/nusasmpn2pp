import 'dart:async';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_card/application/student_card_controller.dart';
import 'package:nusa/features/student_card/data/student_card_image_saver.dart';
import 'package:nusa/features/student_card/domain/student_card.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';
import 'package:qr_flutter/qr_flutter.dart';

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
    _debounce = Timer(const Duration(milliseconds: 450), () {
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

enum StudentCardSide { front, back }

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

class _StudentCardPreviewViewState
    extends ConsumerState<StudentCardPreviewView> {
  final _captureKey = GlobalKey();
  StudentCardSide _side = StudentCardSide.front;
  bool _saving = false;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: NusaColors.background,
    appBar: AppBar(title: const Text('Pratinjau Kartu')),
    body: SafeArea(
      top: false,
      child: ListView(
        key: const PageStorageKey<String>('student-card-preview'),
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
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
              onSelectionChanged: (selection) {
                setState(() => _side = selection.first);
              },
            ),
          ),
          const SizedBox(height: 14),
          Center(
            child: RepaintBoundary(
              key: _captureKey,
              child: _StudentIdentityCard(
                key: ValueKey(_side),
                student: widget.student,
                side: _side,
                aspectRatio: widget.cardSize.aspectRatio,
              ),
            ),
          ),
          const SizedBox(height: 14),
          _ReadinessNote(student: widget.student),
          const SizedBox(height: 12),
          if (widget.canPrint)
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                key: const Key('save-student-card-png'),
                onPressed: _saving ? null : _savePng,
                icon: _saving
                    ? const SizedBox.square(
                        dimension: 17,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : const Icon(Icons.download_rounded),
                label: Text(
                  _saving
                      ? 'Menyimpan...'
                      : 'Simpan PNG Sisi ${_side == StudentCardSide.front ? 'Depan' : 'Belakang'}',
                ),
              ),
            )
          else
            const _ViewOnlyNotice(),
          const SizedBox(height: 8),
          if (widget.canPrint)
            const Text(
              'Untuk mencetak dua sisi, simpan sisi depan dan belakang secara bergantian.',
              textAlign: TextAlign.center,
              style: TextStyle(color: NusaColors.textSecondary, fontSize: 10.5),
            ),
        ],
      ),
    ),
  );

  Future<void> _savePng() async {
    setState(() => _saving = true);
    try {
      final boundary =
          _captureKey.currentContext?.findRenderObject()
              as RenderRepaintBoundary?;
      if (boundary == null) {
        throw StateError('Pratinjau kartu belum siap.');
      }
      final image = await boundary.toImage(pixelRatio: 3);
      final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
      image.dispose();
      if (byteData == null) throw StateError('Gambar kartu belum terbentuk.');
      final side = _side == StudentCardSide.front ? 'depan' : 'belakang';
      final saved = await ref
          .read(studentCardImageSaverProvider)
          .save(
            fileName: 'kartu-pelajar-${_slug(widget.student.name)}-$side.png',
            bytes: byteData.buffer.asUint8List(),
          );
      if (!mounted || !saved) return;
      _showMessage('Kartu sisi $side berhasil disimpan.');
    } catch (error) {
      if (mounted) _showMessage('Kartu belum berhasil disimpan. Coba kembali.');
    } finally {
      if (mounted) setState(() => _saving = false);
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

class _StudentIdentityCard extends StatelessWidget {
  const _StudentIdentityCard({
    required this.student,
    required this.side,
    required this.aspectRatio,
    super.key,
  });

  final StudentCardPerson student;
  final StudentCardSide side;
  final double aspectRatio;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      final width = constraints.maxWidth.clamp(0.0, 330.0);
      return SizedBox(
        width: width,
        height: width / aspectRatio,
        child: FittedBox(
          fit: BoxFit.fill,
          child: SizedBox(
            width: 330,
            height: 330 / aspectRatio,
            child: side == StudentCardSide.front
                ? _StudentCardFront(student: student)
                : _StudentCardBack(student: student),
          ),
        ),
      );
    },
  );
}

class _StudentCardFront extends StatelessWidget {
  const _StudentCardFront({required this.student});

  final StudentCardPerson student;

  @override
  Widget build(BuildContext context) => _CardBackground(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(23, 22, 23, 20),
      child: Column(
        children: [
          _CardLogo(size: 58),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 7),
            decoration: BoxDecoration(
              color: NusaColors.primary.withValues(alpha: 0.82),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: NusaColors.accent, width: 1.4),
            ),
            child: const Text(
              'KARTU PELAJAR',
              style: TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 18),
          Container(
            width: 137,
            height: 161,
            padding: const EdgeInsets.all(5),
            decoration: BoxDecoration(
              color: NusaColors.accent,
              borderRadius: BorderRadius.circular(22),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x55000000),
                  blurRadius: 18,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(18),
              child: Container(
                color: Colors.white,
                child: student.photoUrl?.isNotEmpty == true
                    ? Image.network(
                        student.photoUrl!,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) =>
                            _CardPhotoFallback(initials: student.initials),
                      )
                    : _CardPhotoFallback(initials: student.initials),
              ),
            ),
          ),
          const SizedBox(height: 19),
          SizedBox(
            width: 282,
            height: 30,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Text(
                student.name.toUpperCase(),
                maxLines: 1,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 21,
                  fontWeight: FontWeight.w900,
                  shadows: [Shadow(color: Colors.black38, blurRadius: 5)],
                ),
              ),
            ),
          ),
          const SizedBox(height: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 15, vertical: 7),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              student.nisnLabel,
              style: const TextStyle(
                color: NusaColors.primary,
                fontSize: 11,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 9),
          Text(
            student.birthLabel,
            maxLines: 2,
            textAlign: TextAlign.center,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
          const Spacer(),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 9),
            decoration: BoxDecoration(
              color: NusaColors.accent,
              borderRadius: BorderRadius.circular(11),
            ),
            child: const Text(
              'SMP NEGERI 2 PADANG PANJANG',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: NusaColors.primary,
                fontSize: 9,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _StudentCardBack extends StatelessWidget {
  const _StudentCardBack({required this.student});

  final StudentCardPerson student;

  @override
  Widget build(BuildContext context) => _CardBackground(
    reverse: true,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(24, 24, 24, 22),
      child: Column(
        children: [
          _CardLogo(size: 76, rounded: true),
          const SizedBox(height: 11),
          const Text(
            'PRESENSI SISWA NUSA',
            style: TextStyle(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 18),
          Container(
            width: 246,
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(24),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x44000000),
                  blurRadius: 20,
                  offset: Offset(0, 9),
                ),
              ],
            ),
            child: Column(
              children: [
                SizedBox(
                  width: 178,
                  height: 178,
                  child: student.canMakeQr && student.qrData != null
                      ? QrImageView(
                          key: const Key('student-card-qr'),
                          data: student.qrData!,
                          version: QrVersions.auto,
                          padding: EdgeInsets.zero,
                          backgroundColor: Colors.white,
                        )
                      : const _QrUnavailable(),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: 214,
                  height: 24,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(
                      student.name.toUpperCase(),
                      maxLines: 1,
                      style: const TextStyle(
                        color: NusaColors.primary,
                        fontSize: 14,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
                Text(
                  student.nisnLabel,
                  style: const TextStyle(
                    color: Color(0xFF385873),
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const Spacer(),
          const Text(
            'Scan QR ini untuk layanan presensi siswa\nSMP Negeri 2 Padang Panjang.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white,
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
              height: 1.3,
            ),
          ),
        ],
      ),
    ),
  );
}

class _CardBackground extends StatelessWidget {
  const _CardBackground({required this.child, this.reverse = false});

  final Widget child;
  final bool reverse;

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(20),
    child: DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: reverse ? Alignment.bottomLeft : Alignment.topLeft,
          end: reverse ? Alignment.topRight : Alignment.bottomRight,
          colors: const [
            Color(0xFF062E58),
            NusaColors.primary,
            Color(0xFF0A5C9F),
          ],
        ),
        border: Border.all(color: Colors.white54, width: 1.2),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Positioned(
            top: -72,
            left: -65,
            child: Container(
              width: 190,
              height: 190,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.08),
                border: Border.all(color: NusaColors.accent, width: 1.2),
              ),
            ),
          ),
          Positioned(
            right: -82,
            bottom: -84,
            child: Container(
              width: 220,
              height: 220,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.06),
                border: Border.all(color: NusaColors.accent, width: 1.2),
              ),
            ),
          ),
          child,
        ],
      ),
    ),
  );
}

class _CardLogo extends StatelessWidget {
  const _CardLogo({required this.size, this.rounded = false});

  final double size;
  final bool rounded;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    padding: EdgeInsets.all(size * 0.11),
    decoration: BoxDecoration(
      color: Colors.white,
      shape: rounded ? BoxShape.rectangle : BoxShape.circle,
      borderRadius: rounded ? BorderRadius.circular(size * 0.22) : null,
      border: Border.all(color: NusaColors.accent, width: 1.5),
      boxShadow: const [
        BoxShadow(color: Colors.black26, blurRadius: 8, offset: Offset(0, 3)),
      ],
    ),
    child: Image.asset('assets/images/logo-nusa.png', fit: BoxFit.contain),
  );
}

class _CardPhotoFallback extends StatelessWidget {
  const _CardPhotoFallback({required this.initials});

  final String initials;

  @override
  Widget build(BuildContext context) => Container(
    color: const Color(0xFFE8F1FA),
    alignment: Alignment.center,
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.person_rounded, size: 65, color: NusaColors.primary),
        const SizedBox(height: 5),
        Text(
          initials.isEmpty ? 'SW' : initials,
          style: const TextStyle(
            color: NusaColors.primary,
            fontSize: 16,
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    ),
  );
}

class _QrUnavailable extends StatelessWidget {
  const _QrUnavailable();

  @override
  Widget build(BuildContext context) => const DecoratedBox(
    decoration: BoxDecoration(color: Color(0xFFF2F5F8)),
    child: Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(Icons.qr_code_2_rounded, size: 70, color: NusaColors.outline),
        SizedBox(height: 7),
        Text(
          'QR BELUM TERSEDIA',
          style: TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
        ),
        SizedBox(height: 3),
        Text(
          'NISN harus berupa angka',
          style: TextStyle(color: NusaColors.textSecondary, fontSize: 8),
        ),
      ],
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
