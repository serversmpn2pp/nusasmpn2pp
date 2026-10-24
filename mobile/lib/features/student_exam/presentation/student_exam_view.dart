import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:nusa/core/errors/app_exception.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_exam/application/student_exam_controller.dart';
import 'package:nusa/features/student_exam/application/exam_navigation_guard.dart';
import 'package:nusa/features/student_exam/data/exam_security_platform.dart';
import 'package:nusa/features/student_exam/data/exam_security_journal.dart';
import 'package:nusa/features/student_exam/data/exam_answer_draft_store.dart';
import 'package:nusa/features/student_exam/data/student_exam_file_picker.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';
import 'package:nusa/features/student_exam/presentation/student_exam_question_view.dart';
import 'package:nusa/shared/widgets/nusa_form_widgets.dart';

class StudentExamView extends ConsumerStatefulWidget {
  const StudentExamView({required this.participantId, super.key});

  final int participantId;

  @override
  ConsumerState<StudentExamView> createState() => _StudentExamViewState();
}

class _StudentExamViewState extends ConsumerState<StudentExamView>
    with WidgetsBindingObserver {
  final _tokenController = TextEditingController();
  final _pageController = PageController();
  final Map<String, TextEditingController> _answerControllers = {};
  final Map<int, Timer> _saveTimers = {};
  final Map<int, int> _revisions = {};
  final Set<int> _dirtyQuestions = {};
  final Map<int, _AnswerSaveStatus> _saveStatuses = {};
  final Set<int> _uploadingQuestions = {};
  late final ExamSecurityPlatform _securityPlatform;
  late final ExamNavigationGuard _navigationGuard;
  StudentExamSession? _session;
  Timer? _countdownTimer;
  Timer? _heartbeatTimer;
  Timer? _environmentTimer;
  bool _multiWindow = false;
  ExamSecurityJournal? _journal;
  Future<void>? _journalReady;
  bool _appForeground = true;
  Timer? _pickerTimer;
  Stopwatch? _pickerClock;
  StudentExamFilePermitResult? _filePermit;
  String? _pickerRequestId;
  int? _pickerQuestionId;
  bool _pickerOpen = false;
  bool _pickerExpired = false;
  String _pickerOutcome = 'dibatalkan';
  Future<bool>? _pickerClosing;
  bool _securitySyncPending = false;
  bool _syncingServerCompletion = false;
  DateTime? _localDeadline;
  int _remainingSeconds = 0;
  int _currentQuestion = 0;
  bool _opening = false;
  bool _finishing = false;
  bool _automaticFinishStarted = false;
  bool _allowPop = false;
  bool _awayReported = false;
  int _resumesInFlight = 0;
  bool get _resuming => _resumesInFlight > 0;
  bool _initialLockedSessionScheduled = false;
  bool _initialCompletedSessionScheduled = false;
  bool _pickingAnswerFile = false;
  bool _leaving = false;
  bool _screenReady = false;
  bool _screenChecking = false;
  bool _screenFailureReported = false;
  String? _screenError;
  int _screenGeneration = 0;
  bool _environmentChecking = false;
  late final ExamAnswerDraftStore _draftStore;
  ExamDraftSession? _draftSession;
  final Map<int, ExamAnswerDraft> _drafts = {};
  final Map<int, Future<void>> _draftWrites = {};
  final Map<int, Future<bool>> _answerFlights = {};
  final Map<int, ExamAnswerDraft> _draftConflicts = {};
  bool _draftLoading = false;
  String? _draftError;
  int _draftGeneration = 0;

  @override
  void initState() {
    super.initState();
    _securityPlatform = ref.read(examSecurityPlatformProvider);
    _navigationGuard = ref.read(examNavigationGuardProvider);
    _draftStore = ref.read(examAnswerDraftStoreProvider);
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _appForeground = state == AppLifecycleState.resumed;
    if (_session?.isRunning == true && _session!.security.enabled) {
      setState(() => _screenReady = false);
    }
    if (_pickerOpen &&
        !_pickerExpired &&
        (_pickerClock?.elapsed.inSeconds ?? 1 << 30) <
            (_filePermit?.remainingSeconds ?? 0) &&
        state != AppLifecycleState.resumed) {
      return; // Only a live, server-approved picker window can exempt this transition.
    }
    if (state == AppLifecycleState.resumed) {
      _updateCountdown();
      unawaited(_resumeWithProtection());
      return;
    }
    if (state == AppLifecycleState.inactive ||
        state == AppLifecycleState.hidden ||
        state == AppLifecycleState.paused) {
      _handleAway();
    }
  }

  @override
  void dispose() {
    _navigationGuard.release(this);
    final journal = _journal;
    final ready = _journalReady;
    if (!_allowPop && _session?.isCompleted != true && journal != null) {
      // Durable evidence for forced route disposal/auth redirects/process recovery.
      unawaited(
        (ready ?? Future<void>.value())
            .then(
              (_) => journal.record(
                widget.participantId,
                'pemulihan',
                metadata: {'pemicu': 'halaman-ditutup'},
              ),
            )
            .catchError((Object _) {}),
      );
    }
    WidgetsBinding.instance.removeObserver(this);
    _countdownTimer?.cancel();
    _heartbeatTimer?.cancel();
    _environmentTimer?.cancel();
    _pickerTimer?.cancel();
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    for (final controller in _answerControllers.values) {
      controller.dispose();
    }
    _tokenController.dispose();
    _pageController.dispose();
    _screenGeneration++;
    _draftGeneration++;
    unawaited(_securityPlatform.leave().catchError((Object _) {}));
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(studentExamProvider(widget.participantId));
    final data = _session ?? state.value;
    if (data?.isCompleted == true &&
        data!.security.trackAppSwitch &&
        _journal == null &&
        !_initialCompletedSessionScheduled) {
      _initialCompletedSessionScheduled = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        _journal = ref.read(examSecurityJournalProvider);
        _journalReady = _journal!.begin(widget.participantId);
        unawaited(_completeJournal());
      });
    }
    if (data?.isCompleted == true && !_draftLoading && _draftGeneration == 0) {
      _draftGeneration++;
      unawaited(
        _draftStore
            .clearParticipant(widget.participantId)
            .catchError((Object _) {}),
      );
    }
    if (data?.isLocked == true &&
        _session == null &&
        !_initialLockedSessionScheduled) {
      _initialLockedSessionScheduled = true;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted && _session == null) _activate(data!);
      });
    }
    return PopScope(
      canPop:
          !_opening &&
          (_allowPop ||
              (data?.isRunning != true &&
                  !(data?.isLocked == true && _dirtyQuestions.isNotEmpty))),
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) unawaited(_confirmLeave());
      },
      child: Scaffold(
        backgroundColor: NusaColors.background,
        appBar: AppBar(
          title: Text(
            data?.isRunning == true || data?.isLocked == true
                ? 'Ujian'
                : 'Ujian Saya',
          ),
          actions: [
            if (data?.isRunning == true || data?.isLocked == true)
              Padding(
                padding: const EdgeInsets.only(right: 12),
                child: Center(child: _TimerPill(seconds: _remainingSeconds)),
              )
            else if (!state.isLoading)
              IconButton(
                tooltip: 'Perbarui',
                onPressed: () {
                  setState(() => _session = null);
                  ref.invalidate(studentExamProvider(widget.participantId));
                },
                icon: const Icon(Icons.refresh_rounded),
              ),
          ],
        ),
        body: SafeArea(
          top: false,
          child: data != null
              ? _content(data)
              : state.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (error, stackTrace) => _ErrorState(
                    message: _message(error),
                    onRetry: () => ref.invalidate(
                      studentExamProvider(widget.participantId),
                    ),
                  ),
                  data: _content,
                ),
        ),
      ),
    );
  }

  Widget _content(StudentExamSession session) {
    if (!session.isCompleted &&
        !session.isLocked &&
        (_pickingAnswerFile || _pickerRequestId != null)) {
      return _filePickerGate();
    }
    if (!session.isCompleted &&
        !session.isLocked &&
        (_screenChecking ||
            _screenError != null ||
            _multiWindow ||
            (session.isRunning &&
                session.security.enabled &&
                (!_screenReady || !_appForeground)))) {
      return _protectionGate(session);
    }
    if (session.isRunning &&
        (_draftLoading || _draftError != null || _draftConflicts.isNotEmpty)) {
      return _draftGate();
    }
    if (session.isRunning) return _running(session);
    if (session.isLocked) return _locked(session);
    if (session.isCompleted) return _completed(session);
    return _confirmation(session);
  }

  Widget _draftGate() => ListView(
    key: const Key('student-exam-draft-gate'),
    padding: const EdgeInsets.all(24),
    children: [
      const Icon(
        Icons.enhanced_encryption_outlined,
        size: 56,
        color: NusaColors.primary,
      ),
      const SizedBox(height: 16),
      Text(
        _draftLoading
            ? 'Memeriksa draft jawaban'
            : _draftConflicts.isNotEmpty
            ? 'Jawaban server sudah berubah'
            : 'Penyimpanan draft belum siap',
        textAlign: TextAlign.center,
        style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 12),
      Text(
        _draftConflicts.isNotEmpty
            ? 'Ada ${_draftConflicts.length} draft HP yang berbeda dengan versi server. Pilih jawaban yang digunakan; draft tidak menimpa server secara otomatis. Waktu ujian tetap berjalan.'
            : _draftError ?? 'Memeriksa sesi server dan draft terenkripsi sebelum membuka soal.',
        textAlign: TextAlign.center,
      ),
      const SizedBox(height: 16),
      if (_draftLoading)
        const Center(child: CircularProgressIndicator())
      else if (_draftConflicts.isNotEmpty) ...[
        FilledButton(
          onPressed: () => _resolveDraftConflicts(useLocal: true),
          child: const Text('Pulihkan Draft HP'),
        ),
        TextButton(
          onPressed: () => _resolveDraftConflicts(useLocal: false),
          child: const Text('Pakai Jawaban Server'),
        ),
      ] else
        FilledButton(
          onPressed: _retryDrafts,
          child: const Text('Periksa Penyimpanan Lagi'),
        ),
      const SizedBox(height: 12),
      const Text(
        'Jika tetap gagal, hubungi pengawas. Jangan hapus data aplikasi; draft yang belum terkirim dapat hilang.',
        textAlign: TextAlign.center,
      ),
    ],
  );

  Widget _filePickerGate() => ListView(
    key: const Key('student-exam-file-permit-gate'),
    padding: const EdgeInsets.all(24),
    children: [
      const Icon(
        Icons.folder_copy_outlined,
        size: 64,
        color: NusaColors.primary,
      ),
      const SizedBox(height: 16),
      Text(
        _pickerExpired
            ? 'Waktu pemilih berkas habis'
            : 'Pemilih berkas jawaban',
        textAlign: TextAlign.center,
        style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 12),
      Text(
        _pickerOpen
            ? 'Tutup pemilih berkas untuk kembali ke NUSA. Izin hanya berlaku sementara; waktu ujian tetap berjalan.'
            : _pickingAnswerFile
            ? 'Memeriksa izin server, menyimpan jawaban, dan memeriksa kembali proteksi layar. Soal disembunyikan selama proses ini.'
            : 'Izin berkas belum terkonfirmasi selesai. Periksa koneksi lalu konfirmasikan kembali. Berkas yang baru dipilih perlu dipilih ulang; jawaban yang sudah tersimpan tetap ada.',
        textAlign: TextAlign.center,
      ),
      if (!_pickingAnswerFile && _pickerRequestId != null) ...[
        const SizedBox(height: 16),
        FilledButton.icon(
          key: const Key('student-exam-reconcile-file-permit'),
          onPressed: _reconcileFilePermit,
          icon: const Icon(Icons.sync),
          label: const Text('Konfirmasi Kembali ke Ujian'),
        ),
      ],
      const SizedBox(height: 16),
      const Text(
        'Jika tetap terkendala, hubungi pengawas. Gangguan ini bukan penetapan kecurangan.',
        textAlign: TextAlign.center,
      ),
    ],
  );

  Widget _protectionGate(StudentExamSession session) => ListView(
    key: const Key('student-exam-protection-gate'),
    padding: const EdgeInsets.all(24),
    children: [
      const Icon(Icons.shield_outlined, size: 64, color: NusaColors.primary),
      const SizedBox(height: 18),
      Text(
        _screenChecking
            ? 'Memeriksa proteksi layar'
            : 'Proteksi layar belum siap',
        textAlign: TextAlign.center,
        style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 12),
      Text(
        _multiWindow
            ? 'Tutup layar terbagi untuk melanjutkan ujian.'
            : _screenError ?? 'Soal belum ditampilkan sampai pemeriksaan proteksi layar selesai.',
        textAlign: TextAlign.center,
      ),
      const SizedBox(height: 12),
      Text(
        session.participant.status == 'aktif'
            ? 'Pemeriksaan ini dilakukan sebelum meminta mulai ujian ke server.'
            : 'Waktu ujian di server tetap berjalan. Ini masalah teknis, bukan penetapan kecurangan.',
        textAlign: TextAlign.center,
      ),
      const SizedBox(height: 18),
      if (_securitySyncPending)
        const Text(
          'Koneksi terputus. Catatan ujian tersimpan di HP dan akan dikirim ulang. Jika berlanjut, hubungi pengawas.',
          key: Key('student-exam-security-pending'),
        ),
      if (_screenChecking)
        const Center(child: CircularProgressIndicator())
      else
        FilledButton.icon(
          key: const Key('student-exam-retry-protection'),
          onPressed: () => _applyPlatformSecurity(session),
          icon: const Icon(Icons.refresh),
          label: const Text('Periksa Lagi'),
        ),
      const SizedBox(height: 12),
      const Text(
        'Jika tetap gagal, hubungi pengawas untuk memeriksa perangkat atau menggunakan perangkat pengganti yang disetujui.',
        textAlign: TextAlign.center,
      ),
    ],
  );

  Widget _confirmation(StudentExamSession session) => ListView(
    key: const Key('student-exam-confirmation'),
    keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
    children: [
      _ExamHero(exam: session.exam, participant: session.participant),
      const SizedBox(height: 12),
      _ProgressCard(progress: session.progress),
      const SizedBox(height: 12),
      _InformationCard(session: session),
      const SizedBox(height: 12),
      _InstructionCard(instructions: session.exam.instructions),
      if (session.security.trackAppSwitch &&
          session.security.graceSeconds == 0) ...[
        const SizedBox(height: 12),
        const _Notice(
          message: 'Nol toleransi: setiap kehilangan fokus yang terdeteksi dihitung satu kejadian, termasuk gangguan telepon yang mengalihkan layar atau layar mati. Siapkan perangkat sebelum mulai.',
          warning: true,
        ),
      ],
      if (session.requiresToken) ...[
        const SizedBox(height: 12),
        NusaTextField(
          fieldKey: const Key('student-exam-token'),
          controller: _tokenController,
          hintText: 'Token dari pengawas',
          labelText: 'Token ujian',
          prefixIcon: Icons.key_rounded,
          textInputAction: TextInputAction.done,
          onFieldSubmitted: (_) => _openExam(session),
        ),
      ],
      const SizedBox(height: 16),
      NusaPrimaryButton(
        key: const Key('student-exam-open'),
        label: session.participant.status == 'sedang_mengerjakan'
            ? 'Lanjutkan Ujian'
            : 'Mulai Ujian',
        loading: _opening,
        onPressed: session.canStart ? () => _openExam(session) : null,
      ),
      if (!session.canStart) ...[
        const SizedBox(height: 10),
        const _Notice(
          message: 'Ujian belum dapat dimulai. Periksa kembali jadwal atau hubungi pengawas.',
          warning: true,
        ),
      ],
    ],
  );

  Widget _running(StudentExamSession session) {
    if (session.questions.isEmpty) {
      return const _ErrorState(
        message: 'Paket ujian belum memiliki soal yang dapat ditampilkan.',
      );
    }
    final current = session
        .questions[_currentQuestion.clamp(0, session.questions.length - 1)];
    final progress = _progressFor(session.questions);
    final canFinish = _remainingSeconds <= 15 * 60;
    final finishAvailability = canFinish
        ? progress.unanswered == 0
              ? '15 menit terakhir. Semua soal lengkap dan ujian dapat dikumpulkan.'
              : '15 menit terakhir. Ujian sudah dapat dikumpulkan.'
        : progress.unanswered == 0
        ? 'Semua soal sudah lengkap. Periksa kembali; tombol aktif pada 15 menit terakhir.'
        : 'Tombol kumpulkan aktif pada 15 menit terakhir.';
    return Column(
      children: [
        if (_multiWindow)
          const Padding(
            padding: EdgeInsets.all(16),
            child: Text('Tutup layar terbagi untuk melanjutkan ujian.'),
          ),
        if (session.security.enabled)
          _SafeModeBanner(security: session.security),
        if (_securitySyncPending)
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            child: Text(
              'Koneksi terputus. Catatan ujian tersimpan di HP dan akan dikirim ulang. Jika berlanjut, hubungi pengawas.',
              key: Key('student-exam-security-pending'),
              style: TextStyle(fontSize: 12, color: NusaColors.textSecondary),
            ),
          ),
        _ExamStatusBar(
          subject: session.exam.subject,
          current: _currentQuestion + 1,
          total: session.questions.length,
          progress: session.progress,
          onNavigate: () => _showQuestionNavigator(session),
        ),
        Expanded(
          child: _multiWindow
              ? const Center(
                  child: Icon(Icons.phonelink_erase_rounded, size: 48),
                )
              : PageView.builder(
                  key: const Key('student-exam-question-pages'),
                  controller: _pageController,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: session.questions.length,
                  itemBuilder: (context, index) => StudentExamQuestionView(
                    question: session.questions[index],
                    statusIndicator: _SaveStatusLabel(
                      status:
                          _saveStatuses[session.questions[index].id] ??
                          _AnswerSaveStatus.saved,
                    ),
                    controllerFor: _controllerFor,
                    onAnswerChanged: (answer) =>
                        _changeAnswer(session.questions[index], answer),
                    onDoubtChanged: (value) =>
                        _changeDoubt(session.questions[index], value),
                    uploadingFile: _uploadingQuestions.contains(
                      session.questions[index].id,
                    ),
                    onUploadFile: () =>
                        _pickAndUploadAnswerFile(session.questions[index]),
                  ),
                ),
        ),
        _ExamNavigationBar(
          current: _currentQuestion,
          total: session.questions.length,
          finishing: _finishing,
          canFinish: canFinish,
          finishAvailability: finishAvailability,
          saveStatus: _saveStatuses[current.id] ?? _AnswerSaveStatus.saved,
          onPrevious: _currentQuestion > 0
              ? () => _goToQuestion(_currentQuestion - 1)
              : null,
          onNext: _currentQuestion < session.questions.length - 1
              ? () => _goToQuestion(_currentQuestion + 1)
              : null,
          onFinish: () => _confirmFinish(session),
        ),
      ],
    );
  }

  Widget _completed(StudentExamSession session) => ListView(
    key: const Key('student-exam-completed'),
    padding: const EdgeInsets.fromLTRB(16, 18, 16, 30),
    children: [
      const _CompletionIcon(),
      const SizedBox(height: 18),
      Text(
        'Ujian telah selesai',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.headlineSmall
            ?.copyWith(color: NusaColors.primary, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 6),
      Text(
        'Jawaban ${session.participant.name} sudah tersimpan di NUSA.',
        textAlign: TextAlign.center,
        style: const TextStyle(color: NusaColors.textSecondary),
      ),
      const SizedBox(height: 20),
      _ExamHero(exam: session.exam, participant: session.participant),
      const SizedBox(height: 12),
      _ProgressCard(progress: session.progress),
      const SizedBox(height: 12),
      _ResultCard(result: session.result),
      const SizedBox(height: 18),
      FilledButton.icon(
        key: const Key('student-exam-back-to-list'),
        onPressed: () => context.pop(),
        icon: const Icon(Icons.arrow_back_rounded),
        label: const Text('Kembali ke Ujian Saya'),
      ),
    ],
  );

  Widget _locked(StudentExamSession session) => ListView(
    key: const Key('student-exam-locked'),
    padding: const EdgeInsets.fromLTRB(16, 20, 16, 30),
    children: [
      const Icon(Icons.gpp_bad_rounded, size: 74, color: Color(0xFFC0392B)),
      const SizedBox(height: 14),
      Text(
        'Ujian sementara ditahan',
        textAlign: TextAlign.center,
        style: Theme.of(context).textTheme.headlineSmall
            ?.copyWith(color: NusaColors.primary, fontWeight: FontWeight.w900),
      ),
      const SizedBox(height: 8),
      Text(
        session.security.holdMessage ?? 'Ujian sementara ditahan. Waktu ujian tetap berjalan. Silakan hubungi pengawas.',
        textAlign: TextAlign.center,
        style: TextStyle(color: NusaColors.textSecondary, height: 1.45),
      ),
      const SizedBox(height: 18),
      _SafeModeBanner(security: session.security, locked: true),
      const SizedBox(height: 18),
      FilledButton.icon(
        key: const Key('student-exam-check-lock'),
        onPressed: _opening ? null : _checkLockStatus,
        icon: _opening
            ? const SizedBox.square(
                dimension: 17,
                child: CircularProgressIndicator(
                  strokeWidth: 2,
                  color: Colors.white,
                ),
              )
            : const Icon(Icons.sync_rounded),
        label: const Text('Periksa Status Ujian'),
      ),
      const SizedBox(height: 10),
      TextButton.icon(
        onPressed: () => context.pop(),
        icon: const Icon(Icons.arrow_back_rounded),
        label: const Text('Kembali ke daftar ujian'),
      ),
    ],
  );

  Future<void> _openExam(StudentExamSession session) async {
    if (_opening) return;
    if (session.requiresToken && _tokenController.text.trim().isEmpty) {
      _showMessage(
        'Masukkan token dari pengawas terlebih dahulu.',
        error: true,
      );
      return;
    }
    setState(() => _opening = true);
    try {
      if (!await _applyPlatformSecurity(session) || !mounted) return;
      if (session.security.trackAppSwitch) {
        _navigationGuard.protect(widget.participantId, this);
      }
      final actions = ref.read(studentExamActionsProvider);
      final result = session.participant.status == 'sedang_mengerjakan'
          ? await actions.resume(widget.participantId)
          : await actions.start(
              widget.participantId,
              _tokenController.text.trim(),
            );
      if (!mounted) return;
      _activate(result);
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (_session == null) _navigationGuard.release(this);
      if (mounted) setState(() => _opening = false);
    }
  }

  void _activate(StudentExamSession session) {
    final serverSession = session;
    final pendingDrafts =
        _session?.participant.startedAt == session.participant.startedAt
        ? {
            for (final id in _dirtyQuestions)
              if (_drafts[id] != null) id: _drafts[id]!,
          }
        : <int, ExamAnswerDraft>{};
    final draftGeneration = ++_draftGeneration;
    final drafts =
        _session != null &&
            !session.isCompleted &&
            (session.isLocked || (_session!.isLocked && session.isRunning))
        ? {
            for (final q in _session!.questions)
              if (_dirtyQuestions.contains(q.id)) q.id: q,
          }
        : <int, StudentExamQuestion>{};
    if (drafts.isNotEmpty) {
      session = session.copyWith(
        questions: (session.isLocked ? _session!.questions : session.questions)
            .map((q) {
              final draft = drafts[q.id];
              return draft == null
                  ? q
                  : q.copyWith(answer: draft.answer, doubtful: draft.doubtful);
            })
            .toList(growable: false),
      );
    }
    _countdownTimer?.cancel();
    _heartbeatTimer?.cancel();
    _environmentTimer?.cancel();
    _clearAnswerControllers();
    setState(() {
      _session = session;
      _currentQuestion = 0;
      _remainingSeconds = session.remainingSeconds;
      _localDeadline = DateTime.now().add(
        Duration(seconds: session.remainingSeconds),
      );
      _automaticFinishStarted = false;
      _dirtyQuestions.clear();
      _saveStatuses.clear();
      _revisions.clear();
      _screenReady = !session.security.enabled;
      _screenFailureReported = false;
      _screenError = null;
      _draftLoading = session.isRunning;
      _draftError = null;
      _draftConflicts.clear();
      if (session.isLocked) {
        _dirtyQuestions.addAll(drafts.keys);
        for (final id in drafts.keys) {
          _saveStatuses[id] = _AnswerSaveStatus.failed;
        }
      }
    });
    unawaited(_applyPlatformSecurity(session));
    if (session.isRunning && session.security.enabled) {
      _environmentTimer = Timer.periodic(
        const Duration(seconds: 3),
        (_) => unawaited(_checkEnvironment()),
      );
      unawaited(_checkEnvironment());
    }
    if ((session.isRunning || session.isLocked) &&
        session.security.trackAppSwitch &&
        _journal == null) {
      _journal = ref.read(examSecurityJournalProvider);
      _journalReady = _journal!.begin(widget.participantId);
    }
    _syncNavigationGuard();
    if (session.isLocked) unawaited(_flushSecurity().catchError((Object _) {}));
    if (session.isCompleted && _journal != null) {
      unawaited(_completeJournal());
    }
    if (session.isCompleted) {
      _drafts.clear();
      unawaited(
        _draftStore
            .clearParticipant(widget.participantId)
            .catchError((Object _) {}),
      );
    }
    if (session.isRunning || session.isLocked) {
      _countdownTimer = Timer.periodic(
        const Duration(seconds: 1),
        (_) => _updateCountdown(),
      );
    }
    if (session.isRunning) {
      _heartbeatTimer = Timer.periodic(
        const Duration(seconds: 15),
        (_) => unawaited(_heartbeat()),
      );
      unawaited(_heartbeat());
      unawaited(_prepareDrafts(serverSession, draftGeneration, pendingDrafts));
    }
  }

  Future<void> _prepareDrafts(
    StudentExamSession serverSession,
    int generation,
    Map<int, ExamAnswerDraft> pending,
  ) async {
    try {
      await _flushSecurity();
      if (!mounted ||
          generation != _draftGeneration ||
          _session?.isRunning != true) {
        return;
      }
      final bound = await _draftStore.open(serverSession);
      final saved = {
        for (final draft in await _draftStore.load(bound))
          draft.questionId: draft,
      };
      for (final draft in pending.values) {
        await _draftStore.put(bound, draft);
        saved[draft.questionId] = draft;
      }
      if (!mounted ||
          generation != _draftGeneration ||
          _session?.isRunning != true) {
        return;
      }
      final questions = [...serverSession.questions];
      final restored = <int, ExamAnswerDraft>{};
      final conflicts = <int, ExamAnswerDraft>{};
      for (final draft in saved.values) {
        final index = questions.indexWhere(
          (q) => q.id == draft.questionId && q.type == draft.type,
        );
        if (index < 0 || draft.matches(questions[index])) {
          await _draftStore.acknowledge(
            bound,
            draft,
            index < 0 ? '' : questions[index].serverVersion,
          );
          continue;
        }
        final question = questions[index];
        if (draft.serverVersion != question.serverVersion) {
          conflicts[draft.questionId] = draft;
          continue;
        }
        questions[index] = question.copyWith(
          answer: draft.answer,
          doubtful: draft.doubtful,
        );
        restored[draft.questionId] = draft;
      }
      if (!mounted ||
          generation != _draftGeneration ||
          _session?.isRunning != true) {
        return;
      }
      setState(() {
        _draftSession = bound;
        _draftWrites.clear(); // Restored records have completed durable writes.
        _drafts
          ..clear()
          ..addAll(restored)
          ..addAll(conflicts);
        _draftConflicts.addAll(conflicts);
        _session = _session!.copyWith(
          questions: questions,
          progress: _progressFor(questions),
        );
        _dirtyQuestions
          ..clear()
          ..addAll(restored.keys)
          ..addAll(conflicts.keys);
        for (final id in restored.keys) {
          _saveStatuses[id] = _AnswerSaveStatus.localSaved;
        }
      });
      for (final id in restored.keys) {
        _saveTimers[id]?.cancel();
        _saveTimers[id] = Timer(
          const Duration(milliseconds: 700),
          () => unawaited(_saveQuestion(id)),
        );
      }
    } catch (_) {
      if (mounted && generation == _draftGeneration) {
        setState(() {
          _draftError = 'Draft jawaban belum dapat dibaca atau disimpan dengan aman. Periksa penyimpanan HP, koneksi, dan versi backend; lalu coba lagi.';
        });
      }
    } finally {
      if (mounted && generation == _draftGeneration) {
        setState(() => _draftLoading = false);
      }
    }
  }

  Future<void> _retryDrafts() async {
    if (_draftLoading) return;
    setState(() => _draftLoading = true);
    try {
      await _flushSecurity();
      final fresh = await ref
          .read(studentExamActionsProvider)
          .resume(widget.participantId);
      if (mounted) _activate(fresh);
    } catch (_) {
      if (mounted) {
        setState(() {
          _draftLoading = false;
          _draftError = 'Belum dapat memeriksa draft. Pulihkan koneksi atau hubungi pengawas.';
        });
      }
    }
  }

  Future<void> _resolveDraftConflicts({required bool useLocal}) async {
    if (_draftLoading) return;
    final conflicts = Map<int, ExamAnswerDraft>.from(_draftConflicts);
    setState(() => _draftLoading = true);
    try {
      await _flushSecurity();
      final fresh = await ref
          .read(studentExamActionsProvider)
          .resume(widget.participantId);
      if (!mounted) return;
      if (!fresh.isRunning || fresh.remainingSeconds <= 0) {
        _activate(fresh);
        return;
      }
      final bound = await _draftStore.open(fresh);
      for (final draft in conflicts.values) {
        final question = fresh.questions
            .where((q) => q.id == draft.questionId && q.type == draft.type)
            .firstOrNull;
        if (useLocal && question != null) {
          final restored = ExamAnswerDraft.fromQuestion(
            question.copyWith(
              answer: question.type == 'upload_file'
                  ? question.answer
                  : draft.answer,
              doubtful: draft.doubtful,
            ),
          );
          await _draftStore.put(bound, restored);
        } else {
          await _draftStore.acknowledge(
            bound,
            draft,
            question?.serverVersion ?? '',
          );
        }
        _drafts.remove(draft.questionId);
        _dirtyQuestions.remove(draft.questionId);
      }
      if (mounted) _activate(fresh);
    } catch (_) {
      if (mounted) {
        setState(() {
          _draftLoading = false;
          _draftError = 'Pemulihan draft belum berhasil. Jawaban server tidak ditimpa. Periksa koneksi atau hubungi pengawas.';
        });
      }
    }
  }

  void _syncNavigationGuard() {
    final session = _session;
    if (!_allowPop &&
        session?.security.trackAppSwitch == true &&
        (session!.isRunning ||
            (session.isLocked && _dirtyQuestions.isNotEmpty))) {
      _navigationGuard.protect(widget.participantId, this);
    } else {
      _navigationGuard.release(this);
    }
  }

  void _updateCountdown() {
    if (!mounted ||
        _localDeadline == null ||
        (_session?.isRunning != true && _session?.isLocked != true)) {
      return;
    }
    final remaining = _localDeadline!.difference(DateTime.now()).inSeconds;
    final next = remaining.clamp(0, 1 << 31);
    if (next != _remainingSeconds) setState(() => _remainingSeconds = next);
    if (next == 0 && !_automaticFinishStarted) {
      _automaticFinishStarted = true;
      unawaited(_finishAutomatically());
    }
  }

  void _handleAway() {
    final session = _session;
    if (session == null ||
        !session.isRunning ||
        !session.security.trackAppSwitch ||
        _awayReported) {
      return;
    }
    _awayReported = true;
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    unawaited(_reportAway());
  }

  Future<void> _reportAway() async {
    try {
      // Do not race answer writes against the server's open-away interval.
      // Unsent drafts remain in memory and are retried after reconciliation.
      await _recordSecurity('keluar');
    } catch (_) {
      // Gangguan jaringan ditangani saat aplikasi aktif kembali.
    }
  }

  Future<void> _handleResume() async {
    if (!_awayReported || _session?.security.trackAppSwitch != true) return;
    _awayReported = false;
    _resumesInFlight++;
    try {
      await _recordSecurity('kembali');
    } catch (_) {
      // Evidence remains in the encrypted journal until acknowledged.
    } finally {
      _resumesInFlight--;
    }
  }

  Future<void> _recordSecurity(
    String event, {
    Map<String, dynamic> metadata = const {},
  }) async {
    final journal = _journal;
    if (journal == null) return;
    await _journalReady;
    await journal.record(widget.participantId, event, metadata: metadata);
    await _flushSecurity();
  }

  Future<void> _flushSecurity() async {
    final journal = _journal;
    if (journal == null) return;
    final actions = ref.read(studentExamActionsProvider);
    try {
      await _journalReady;
      final update = await journal.flush(
        widget.participantId,
        (event, metadata) => actions.securityEvent(
          widget.participantId,
          event,
          metadata: metadata,
        ),
      );
      if (!mounted) return;
      if (_securitySyncPending) setState(() => _securitySyncPending = false);
      if (update != null) _applySecurityUpdate(update);
    } catch (_) {
      if (mounted && !_securitySyncPending) {
        setState(() => _securitySyncPending = true);
      }
      rethrow;
    }
  }

  Future<void> _completeJournal() async {
    try {
      await _journalReady;
      await _journal!.complete(widget.participantId);
      await _flushSecurity();
    } catch (_) {
      // Retain pending evidence even if the result page is offline.
    }
  }

  Future<void> _heartbeat() async {
    if (_session?.isRunning != true ||
        !_appForeground ||
        _awayReported ||
        _resuming ||
        _pickingAnswerFile ||
        _pickerRequestId != null) {
      return;
    }
    try {
      await _flushSecurity();
      if (_session?.isRunning != true) return;
      if (!_draftLoading && _draftError == null && _draftConflicts.isEmpty) {
        await _flushAnswers();
      }
      if (_session?.isRunning != true) return;
      final update = await ref
          .read(studentExamActionsProvider)
          .securityEvent(widget.participantId, 'heartbeat');
      if (mounted) _applySecurityUpdate(update, notify: false);
    } catch (_) {
      // Heartbeat bersifat best effort dan tidak mengganggu pengerjaan.
    }
  }

  Future<void> _checkEnvironment() async {
    if (!_appForeground ||
        _session?.isRunning != true ||
        _screenChecking ||
        _environmentChecking) {
      return;
    }
    _environmentChecking = true;
    final generation = _screenGeneration;
    try {
      final status = await _securityPlatform.inspect().timeout(
        const Duration(seconds: 5),
      );
      if (!mounted ||
          _session?.isRunning != true ||
          generation != _screenGeneration ||
          !_appForeground) {
        return;
      }
      if (!status.readyFor(
        requireSecureScreen: _session!.security.secureScreen,
      )) {
        _protectionFailed();
        return;
      }
      final multiWindow = status.multiWindow;
      if (!mounted || multiWindow == _multiWindow) return;
      setState(() => _multiWindow = multiWindow);
      if (multiWindow) {
        await _recordSecurity(
          'pemulihan',
          metadata: {'pemicu': 'layar-terbagi'},
        );
      }
    } catch (_) {
      if (generation == _screenGeneration && _appForeground) {
        _protectionFailed();
      }
    } finally {
      _environmentChecking = false;
    }
  }

  void _applySecurityUpdate(
    StudentExamSecurityUpdate update, {
    bool notify = true,
  }) {
    final session = _session;
    if (session == null || session.isCompleted) return;
    if (update.mode == 'selesai') {
      unawaited(_reloadServerCompletion());
      return;
    }
    final locked = update.mode == 'ditahan' || update.security.locked;
    setState(() {
      _session = session.copyWith(
        mode: locked ? 'ditahan' : session.mode,
        security: update.security,
      );
    });
    if (locked) {
      _heartbeatTimer?.cancel();
      _environmentTimer?.cancel();
      for (final timer in _saveTimers.values) {
        timer.cancel();
      }
    }
    _syncNavigationGuard();
    if (notify && update.counted && update.message?.isNotEmpty == true) {
      _showMessage(update.message!, error: locked);
    }
  }

  Future<void> _reloadServerCompletion() async {
    if (_syncingServerCompletion) return;
    _syncingServerCompletion = true;
    try {
      final result = await ref
          .read(studentExamActionsProvider)
          .resume(widget.participantId);
      if (mounted && result.isCompleted) _activate(result);
    } catch (_) {
      // A later heartbeat retries; the server already rejects any late answer.
    } finally {
      _syncingServerCompletion = false;
    }
  }

  Future<void> _checkLockStatus() async {
    if (_opening) return;
    setState(() => _opening = true);
    try {
      final result = await ref
          .read(studentExamActionsProvider)
          .resume(widget.participantId);
      if (!mounted) return;
      _activate(result);
      _showMessage(
        result.isRunning
            ? 'Ujian sudah dibuka oleh pengawas. Silakan lanjutkan.'
            : 'Ujian masih ditahan. Hubungi pengawas.',
        error: result.isLocked,
      );
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _opening = false);
    }
  }

  Future<void> _resumeWithProtection() async {
    final reconciliation = _handleResume();
    final session = _session;
    if (session != null && session.isRunning) {
      await _applyPlatformSecurity(session);
    }
    await reconciliation;
    if (mounted && _session?.isRunning == true) await _flushAnswers();
  }

  void _protectionFailed() {
    if (!mounted) return;
    setState(() {
      _screenReady = false;
      _screenError = 'NUSA belum dapat memastikan proteksi layar aktif. Coba periksa lagi atau hubungi pengawas. Soal tetap disembunyikan.';
    });
    if (!_screenFailureReported &&
        _session?.isRunning == true &&
        _session!.security.trackAppSwitch) {
      _screenFailureReported = true;
      unawaited(
        _recordSecurity(
          'pemulihan',
          metadata: {'pemicu': 'proteksi-layar-gagal'},
        ).catchError((Object _) {}),
      );
    }
  }

  Future<bool> _applyPlatformSecurity(StudentExamSession session) async {
    final generation = ++_screenGeneration;
    if (!session.security.enabled || session.isCompleted) {
      if (mounted) {
        setState(() {
          _screenReady = true;
          _screenError = null;
          _screenChecking = false;
          _multiWindow = false;
        });
      }
      if (session.isCompleted) {
        unawaited(_securityPlatform.leave().catchError((Object _) {}));
      }
      return true;
    }
    if (mounted) {
      setState(() {
        _screenReady = false;
        _screenChecking = true;
        _screenError = null;
      });
    }
    try {
      await _securityPlatform
          .enter(
            secureScreen: session.security.secureScreen,
            fullscreen: session.security.requireFullscreen,
          )
          .timeout(const Duration(seconds: 5));
      final status = await _securityPlatform.inspect().timeout(
        const Duration(seconds: 5),
      );
      if (!mounted || generation != _screenGeneration || !_appForeground) {
        return false;
      }
      if (!status.readyFor(
        requireSecureScreen: session.security.secureScreen,
      )) {
        _protectionFailed();
        return false;
      }
      setState(() {
        _screenReady = true;
        _multiWindow = status.multiWindow;
        _screenError = null;
      });
      return !status.multiWindow;
    } catch (_) {
      if (mounted && generation == _screenGeneration) _protectionFailed();
      return false;
    } finally {
      if (mounted && generation == _screenGeneration) {
        setState(() => _screenChecking = false);
      }
    }
  }

  void _changeAnswer(StudentExamQuestion question, Map<String, String> answer) {
    _replaceQuestion(question.copyWith(answer: answer));
  }

  void _changeDoubt(StudentExamQuestion question, bool value) {
    final latest = _questionById(question.id) ?? question;
    _replaceQuestion(latest.copyWith(doubtful: value));
  }

  Future<void> _pickAndUploadAnswerFile(StudentExamQuestion question) async {
    if (_pickingAnswerFile ||
        _pickerRequestId != null ||
        _uploadingQuestions.isNotEmpty ||
        _session?.isRunning != true ||
        !_appForeground ||
        (_session!.security.enabled && !_screenReady)) {
      return;
    }
    StudentExamPickedFile? file;
    var mayUpload = false;
    setState(() => _pickingAnswerFile = true);
    try {
      await _flushSecurity();
      if (!await _flushAnswers() ||
          !mounted ||
          _session?.isRunning != true ||
          !_appForeground ||
          _awayReported) {
        return;
      }
      _pickerQuestionId = question.id;
      _pickerRequestId = ExamSecurityJournal.newEventId();
      _pickerOutcome = 'dibatalkan';
      _pickerExpired = false;
      _pickerClosing = null;
      final permit = await _requestFilePermit();
      if (!mounted || !permit.accepted || _session?.isRunning != true) return;
      if (permit.id == null ||
          permit.questionId != question.id ||
          permit.remainingSeconds <= 0 ||
          permit.remainingSeconds > 90) {
        throw const FormatException('Izin pemilih berkas tidak valid.');
      }
      if (!_appForeground || _awayReported) {
        await _closeFilePermit();
        _clearFilePermit();
        return;
      }
      _pickerOpen = true;
      _pickerClock = Stopwatch()..start();
      _pickerTimer = Timer(Duration(seconds: permit.remainingSeconds), () {
        if (!mounted || !_pickerOpen) return;
        setState(() {
          _pickerExpired = true;
          _pickerOutcome = 'masa_habis';
        });
        // Do not abandon the OS picker Future: that could open a second picker.
        unawaited(_closeFilePermit().catchError((Object _) => false));
      });
      try {
        file = await ref.read(studentExamFilePickerProvider).pick();
        if (!_pickerExpired) {
          _pickerOutcome = file == null ? 'dibatalkan' : 'dipilih';
        }
      } catch (error) {
        if (!_pickerExpired) _pickerOutcome = 'gagal';
        if (mounted) _showMessage(_message(error), error: true);
      } finally {
        _pickerOpen = false;
        _pickerTimer?.cancel();
        _pickerClock?.stop();
      }
      if (!mounted) return;
      mayUpload = await _closeFilePermit() && !_pickerExpired;
      if (!mounted) return;
      _clearFilePermit();
      if (_session?.isRunning == true) {
        mayUpload = await _applyPlatformSecurity(_session!) && mayUpload;
      }
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _pickingAnswerFile = false);
    }
    if (!mayUpload ||
        file == null ||
        !mounted ||
        !_appForeground ||
        _awayReported ||
        _session?.isRunning != true ||
        _pickerRequestId != null) {
      return;
    }
    if (file.bytes.length > 10 * 1024 * 1024) {
      _showMessage('Ukuran berkas maksimal 10 MB.', error: true);
      return;
    }

    setState(() {
      _uploadingQuestions.add(question.id);
      _saveStatuses[question.id] = _AnswerSaveStatus.saving;
    });
    try {
      final latest = _questionById(question.id) ?? question;
      final result = await ref
          .read(studentExamActionsProvider)
          .uploadAnswerFile(
            participantId: widget.participantId,
            question: latest,
            file: file,
          );
      if (!mounted) return;

      var session = _session;
      if (session == null || session.isCompleted) return;
      final changed = (_questionById(question.id) ?? latest).copyWith(
        answer: {'berkas': result.fileName},
        serverVersion: result.serverVersion,
      );
      if (result.serverVersion.isEmpty) {
        throw const FormatException('Versi berkas belum dikonfirmasi backend.');
      }
      final pending = _drafts[question.id];
      if (pending != null && _draftSession != null) {
        final updated = ExamAnswerDraft.fromQuestion(changed);
        _drafts[question.id] = updated;
        await _draftStore.put(_draftSession!, updated);
      }
      session = _session;
      if (!mounted || session == null || session.isCompleted) return;
      final confirmedSession = session;
      final questions = confirmedSession.questions
          .map((item) => item.id == changed.id ? changed : item)
          .toList(growable: false);
      setState(() {
        _session = confirmedSession.copyWith(
          questions: questions,
          progress: _progressFor(questions),
        );
        if (pending == null) _dirtyQuestions.remove(question.id);
        _saveStatuses[question.id] = pending == null
            ? _AnswerSaveStatus.saved
            : _AnswerSaveStatus.localSaved;
        if (result.remainingSeconds > 0) {
          _remainingSeconds = result.remainingSeconds;
          _localDeadline = DateTime.now().add(
            Duration(seconds: result.remainingSeconds),
          );
        }
      });
      _showMessage(
        '${result.fileName} berhasil diunggah${result.fileSizeLabel.isEmpty ? '.' : ' (${result.fileSizeLabel}).'}',
      );
    } catch (error) {
      if (mounted) {
        setState(() => _saveStatuses[question.id] = _AnswerSaveStatus.failed);
        _showMessage(_message(error), error: true);
      }
    } finally {
      if (mounted) setState(() => _uploadingQuestions.remove(question.id));
    }
  }

  Future<StudentExamFilePermitResult> _requestFilePermit() async {
    final requestClock = Stopwatch()..start();
    final response = await ref
        .read(studentExamActionsProvider)
        .filePermit(
          participantId: widget.participantId,
          questionId: _pickerQuestionId,
          requestId: _pickerRequestId,
        );
    requestClock.stop();
    // Conservatively subtract the entire round trip; never trust the phone clock.
    final spentSeconds = (requestClock.elapsedMilliseconds / 1000).ceil();
    final permit = StudentExamFilePermitResult(
      accepted: response.accepted,
      update: response.update,
      id: response.id,
      questionId: response.questionId,
      remainingSeconds: (response.remainingSeconds - spentSeconds).clamp(0, 90),
    );
    if (!mounted) return permit;
    _applySecurityUpdate(permit.update);
    _filePermit = permit;
    if (!permit.accepted) {
      _clearFilePermit();
      _showMessage(
        'Izin pemilih berkas tidak tersedia. Hubungi pengawas jika batas percobaan tercapai.',
        error: true,
      );
    }
    return permit;
  }

  Future<bool> _closeFilePermit() {
    final closing = _pickerClosing;
    if (closing != null) return closing;
    final operation = _sendFilePermitClose();
    _pickerClosing = operation;
    return operation;
  }

  Future<bool> _sendFilePermitClose() async {
    try {
      final id = _filePermit?.id;
      if (id == null) throw StateError('Izin berkas belum diketahui.');
      final result = await ref
          .read(studentExamActionsProvider)
          .filePermit(
            participantId: widget.participantId,
            permitId: id,
            outcome: _pickerOutcome,
          );
      if (result.id != id || result.questionId != _pickerQuestionId) {
        throw const FormatException(
          'Konfirmasi izin pemilih berkas tidak sesuai.',
        );
      }
      if (mounted) _applySecurityUpdate(result.update);
      return result.accepted;
    } catch (_) {
      _pickerClosing =
          null; // Same permit ID is retried, never a new exemption.
      rethrow;
    }
  }

  void _clearFilePermit() {
    _pickerRequestId = null;
    _pickerQuestionId = null;
    _filePermit = null;
    _pickerClosing = null;
    _pickerTimer?.cancel();
  }

  Future<void> _reconcileFilePermit() async {
    if (_pickingAnswerFile || _pickerOpen) return;
    setState(() => _pickingAnswerFile = true);
    try {
      if (_filePermit?.id == null) await _requestFilePermit();
      if (!mounted || _pickerRequestId == null) return;
      await _closeFilePermit();
      if (!mounted) return;
      _clearFilePermit();
      if (_session?.isRunning == true) await _applyPlatformSecurity(_session!);
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _pickingAnswerFile = false);
    }
  }

  void _replaceQuestion(StudentExamQuestion changed) {
    final session = _session;
    final bound = _draftSession;
    if (session == null ||
        !session.isRunning ||
        bound == null ||
        _draftLoading ||
        _draftError != null ||
        _draftConflicts.isNotEmpty ||
        !_appForeground ||
        (session.security.enabled && !_screenReady)) {
      return;
    }
    changed = changed.copyWith(
      serverVersion: _questionById(changed.id)?.serverVersion,
    );
    final draft = ExamAnswerDraft.fromQuestion(changed);
    final questions = session.questions
        .map((item) => item.id == changed.id ? changed : item)
        .toList(growable: false);
    final progress = _progressFor(questions);
    final revision = (_revisions[changed.id] ?? 0) + 1;
    _saveTimers[changed.id]?.cancel();
    setState(() {
      _session = session.copyWith(questions: questions, progress: progress);
      _revisions[changed.id] = revision;
      _dirtyQuestions.add(changed.id);
      _saveStatuses[changed.id] = _AnswerSaveStatus.pending;
      _drafts[changed.id] = draft;
    });
    final write = _persistDraft(bound, draft);
    _draftWrites[changed.id] = write;
    unawaited(write.catchError((Object _) {}));
    _saveTimers[changed.id] = Timer(
      const Duration(milliseconds: 700),
      () => unawaited(_saveQuestion(changed.id)),
    );
  }

  Future<void> _persistDraft(
    ExamDraftSession bound,
    ExamAnswerDraft draft,
  ) async {
    try {
      await _draftStore.put(bound, draft);
      if (mounted &&
          _drafts[draft.questionId]?.id == draft.id &&
          _saveStatuses[draft.questionId] == _AnswerSaveStatus.pending) {
        setState(
          () => _saveStatuses[draft.questionId] = _AnswerSaveStatus.localSaved,
        );
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _draftError = 'Perubahan terakhir belum tersimpan aman di HP. Jangan tutup aplikasi; periksa penyimpanan atau hubungi pengawas.',
        );
      }
      rethrow;
    }
  }

  StudentExamProgress _progressFor(List<StudentExamQuestion> questions) {
    final answered = questions.where((item) => item.isAnswered).length;
    return StudentExamProgress(
      questionCount: questions.length,
      answered: answered,
      unanswered: questions.length - answered,
      doubtful: questions.where((item) => item.doubtful).length,
    );
  }

  Future<bool> _saveQuestion(int questionId) {
    final flight = _answerFlights[questionId];
    if (flight != null) return flight;
    final operation = _saveQuestionNow(questionId);
    _answerFlights[questionId] = operation;
    return operation.whenComplete(() => _answerFlights.remove(questionId));
  }

  Future<bool> _saveQuestionNow(int questionId) async {
    final question = _questionById(questionId);
    if (question == null || _session?.isRunning != true) return true;
    if (!_dirtyQuestions.contains(questionId)) return true;
    if (!_appForeground ||
        _awayReported ||
        _resuming ||
        _draftLoading ||
        _draftError != null ||
        _draftConflicts.isNotEmpty ||
        _pickerRequestId != null ||
        (_session!.security.enabled && !_screenReady)) {
      return false;
    }
    final revision = _revisions[questionId] ?? 0;
    final bound = _draftSession;
    final draft = _drafts[questionId];
    if (bound == null || draft == null) return false;
    if (mounted) {
      setState(() => _saveStatuses[questionId] = _AnswerSaveStatus.saving);
    }
    try {
      await _draftWrites[questionId];
      if (!mounted ||
          _session?.isRunning != true ||
          !_appForeground ||
          _awayReported ||
          _resuming ||
          _pickerRequestId != null ||
          _draftLoading ||
          _draftError != null ||
          (_session!.security.enabled && !_screenReady)) {
        return false;
      }
      final result = await ref
          .read(studentExamActionsProvider)
          .saveAnswer(
            participantId: widget.participantId,
            question: question.copyWith(serverVersion: draft.serverVersion),
          );
      if (!mounted) return true;
      if (result.completed && result.session != null) {
        _activate(result.session!);
        return true;
      }
      if (result.serverVersion.isEmpty) {
        setState(
          () => _draftError = 'Backend belum mengonfirmasi versi jawaban. Draft HP tetap disimpan; hubungi pengawas untuk memperbarui backend.',
        );
        return false;
      }
      await _draftStore.acknowledge(bound, draft, result.serverVersion);
      if (!mounted || _session?.isCompleted == true) return true;
      setState(() {
        _session = _session!.copyWith(
          questions: _session!.questions
              .map(
                (q) => q.id == questionId
                    ? q.copyWith(serverVersion: result.serverVersion)
                    : q,
              )
              .toList(),
        );
        final currentDraft = _drafts[questionId];
        if (currentDraft?.id == draft.id) {
          _drafts.remove(questionId);
        } else if (currentDraft?.serverVersion == draft.serverVersion) {
          _drafts[questionId] = currentDraft!.rebase(result.serverVersion);
        }
        if ((_revisions[questionId] ?? 0) == revision) {
          _dirtyQuestions.remove(questionId);
          _saveStatuses[questionId] = _AnswerSaveStatus.saved;
        } else {
          _saveStatuses[questionId] = _AnswerSaveStatus.localSaved;
        }
        if (result.remainingSeconds > 0) {
          _remainingSeconds = result.remainingSeconds;
          _localDeadline = DateTime.now().add(
            Duration(seconds: result.remainingSeconds),
          );
        }
      });
      if ((_revisions[questionId] ?? 0) != revision) {
        _saveTimers[questionId]?.cancel();
        _saveTimers[questionId] = Timer(
          const Duration(milliseconds: 400),
          () => unawaited(_saveQuestion(questionId)),
        );
      }
      return true;
    } catch (error) {
      if (mounted) {
        setState(() => _saveStatuses[questionId] = _AnswerSaveStatus.failed);
      }
      if (error is ValidationException &&
          error.errors.containsKey('versi_jawaban') &&
          mounted) {
        await _retryDrafts();
        return false;
      }
      if (error is AppException &&
          mounted &&
          _appForeground &&
          !_awayReported &&
          !_pickingAnswerFile &&
          _pickerRequestId == null &&
          !_resuming) {
        try {
          final status = await ref
              .read(studentExamActionsProvider)
              .resume(widget.participantId);
          if (mounted && status.isLocked) {
            _applySecurityUpdate(
              StudentExamSecurityUpdate(
                mode: 'ditahan',
                security: status.security,
                counted: false,
                incidentDurationSeconds: 0,
              ),
            );
          }
        } catch (_) {
          /* Draft remains in memory for retry. */
        }
      }
      return false;
    }
  }

  Future<bool> _flushAnswers() async {
    for (final timer in _saveTimers.values) {
      timer.cancel();
    }
    for (var pass = 0; pass < 4 && _dirtyQuestions.isNotEmpty; pass++) {
      for (final id in _dirtyQuestions.toList(growable: false)) {
        if (!await _saveQuestion(id)) return false;
        if (_session?.isCompleted == true) return true;
      }
    }
    return _dirtyQuestions.isEmpty;
  }

  Future<void> _confirmFinish(StudentExamSession session) async {
    if (_finishing) return;
    final progress = _progressFor(session.questions);
    if (_remainingSeconds > 15 * 60) {
      _showMessage(
        'Ujian baru dapat dikumpulkan pada 15 menit terakhir. Gunakan waktu yang tersedia untuk memeriksa kembali jawaban.',
        error: true,
      );
      return;
    }
    final needsAcknowledgement =
        progress.unanswered > 0 || progress.doubtful > 0;
    var acknowledged = false;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Kumpulkan ujian?'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${progress.answered} terjawab, '
                '${progress.unanswered} belum lengkap, dan '
                '${progress.doubtful} ditandai ragu-ragu.',
              ),
              const SizedBox(height: 10),
              const Text(
                'Setelah dikumpulkan, jawaban tidak dapat diubah dan ujian tidak dapat dibuka kembali.',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
              if (needsAcknowledgement) ...[
                const SizedBox(height: 12),
                CheckboxListTile(
                  key: const Key('student-exam-finish-acknowledgement'),
                  contentPadding: EdgeInsets.zero,
                  controlAffinity: ListTileControlAffinity.leading,
                  value: acknowledged,
                  onChanged: (value) =>
                      setDialogState(() => acknowledged = value ?? false),
                  title: const Text(
                    'Saya memahami masih ada jawaban yang belum lengkap atau ragu-ragu.',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                  ),
                ),
              ],
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Periksa Lagi'),
            ),
            FilledButton(
              key: const Key('student-exam-confirm-finish'),
              onPressed: needsAcknowledgement && !acknowledged
                  ? null
                  : () => Navigator.pop(context, true),
              child: const Text('Kumpulkan'),
            ),
          ],
        ),
      ),
    );
    if (confirmed == true) await _finish();
  }

  Future<void> _finish() async {
    if (_finishing) return;
    setState(() => _finishing = true);
    try {
      await _flushSecurity();
      if (_session?.isLocked == true) return;
      final saved = await _flushAnswers();
      if (!saved) {
        _showMessage(
          'Ada jawaban yang belum tersimpan. Periksa koneksi lalu coba lagi.',
          error: true,
        );
        return;
      }
      if (_session?.isCompleted == true) return;
      final result = await ref
          .read(studentExamActionsProvider)
          .finish(widget.participantId);
      if (mounted) _activate(result);
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    } finally {
      if (mounted) setState(() => _finishing = false);
    }
  }

  Future<void> _finishAutomatically() async {
    try {
      try {
        await _flushSecurity();
      } catch (_) {
        // The server deadline still wins; unsent evidence stays on the device.
      }
      final result = await ref
          .read(studentExamActionsProvider)
          .finish(widget.participantId);
      if (!mounted) return;
      _activate(result);
      _showMessage('Waktu habis. Jawaban yang tersimpan telah dikumpulkan.');
    } catch (error) {
      if (mounted) _showMessage(_message(error), error: true);
    }
  }

  Future<void> _confirmLeave() async {
    if (_leaving || _finishing || _opening) return;
    if (_uploadingQuestions.isNotEmpty ||
        _pickingAnswerFile ||
        _pickerRequestId != null) {
      _showMessage(
        'Tunggu unggahan jawaban selesai sebelum keluar.',
        error: true,
      );
      return;
    }
    if (_session?.isLocked == true && _dirtyQuestions.isNotEmpty) {
      _showMessage(
        'Ada jawaban terbaru yang belum terkirim. Minta pengawas membuka sesi agar jawaban dapat disimpan.',
        error: true,
      );
      return;
    }
    _leaving = true;
    try {
      final strict =
          _session?.security.trackAppSwitch == true &&
          _session?.security.action == 'tahan';
      final leave = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Keluar dari pengerjaan?'),
          content: Text(
            strict
                ? 'Jawaban akan disimpan, lalu sesi ditahan oleh server. Untuk melanjutkan, minta pengawas membuka sesi. Waktu ujian tetap berjalan.'
                : 'Ujian tidak akan diselesaikan. Jawaban disimpan, dan keluar dari halaman ini dicatat selama pemantauan aktif. Waktu ujian tetap berjalan.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Tetap di Ujian'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Simpan & Keluar'),
            ),
          ],
        ),
      );
      if (leave != true || !mounted) return;
      await _flushSecurity();
      if (_session?.isLocked == true) return;
      final saved = await _flushAnswers();
      if (!mounted) return;
      if (!saved) {
        _showMessage('Jawaban belum seluruhnya tersimpan.', error: true);
        return;
      }
      if (_session?.security.trackAppSwitch == true &&
          _session?.isRunning == true) {
        await _recordSecurity(
          'keluar_pengerjaan',
          metadata: {'pemicu': 'simpan-dan-keluar'},
        );
        if (!mounted) return;
        if (strict && _session?.isLocked != true) {
          _showMessage(
            'Server belum mengonfirmasi penahanan sesi. Tetap di ujian dan hubungi pengawas.',
            error: true,
          );
          return;
        }
        await _journal?.complete(widget.participantId);
      }
      if (!mounted) return;
      setState(() => _allowPop = true);
      _syncNavigationGuard();
      context.pop();
    } catch (_) {
      if (mounted) {
        _showMessage(
          'Belum dapat menyimpan atau mengonfirmasi keluar ke server. Periksa koneksi lalu coba lagi.',
          error: true,
        );
      }
    } finally {
      _leaving = false;
    }
  }

  void _goToQuestion(int index) {
    if (index < 0 || index >= (_session?.questions.length ?? 0)) return;
    setState(() => _currentQuestion = index);
    _pageController.animateToPage(
      index,
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOutCubic,
    );
  }

  Future<void> _showQuestionNavigator(StudentExamSession session) async {
    final index = await showModalBottomSheet<int>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 0, 18, 22),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Navigasi Soal',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 5),
              const Text(
                'Biru: aktif · Hijau: terjawab · Kuning: ragu-ragu',
                style: TextStyle(color: NusaColors.textSecondary, fontSize: 11),
              ),
              const SizedBox(height: 14),
              Flexible(
                child: GridView.builder(
                  shrinkWrap: true,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 6,
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                  ),
                  itemCount: session.questions.length,
                  itemBuilder: (context, itemIndex) {
                    final question = session.questions[itemIndex];
                    final color = itemIndex == _currentQuestion
                        ? NusaColors.primary
                        : question.doubtful
                        ? NusaColors.accent
                        : question.isAnswered
                        ? NusaColors.success
                        : NusaColors.outline;
                    final foreground = color == NusaColors.outline
                        ? NusaColors.textPrimary
                        : itemIndex == _currentQuestion || question.isAnswered
                        ? Colors.white
                        : NusaColors.textPrimary;
                    return InkWell(
                      onTap: () => Navigator.pop(context, itemIndex),
                      borderRadius: BorderRadius.circular(10),
                      child: Container(
                        alignment: Alignment.center,
                        decoration: BoxDecoration(
                          color: color,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          '${itemIndex + 1}',
                          style: TextStyle(
                            color: foreground,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
    if (index != null) _goToQuestion(index);
  }

  StudentExamQuestion? _questionById(int id) {
    for (final item in _session?.questions ?? const <StudentExamQuestion>[]) {
      if (item.id == id) return item;
    }
    return null;
  }

  TextEditingController _controllerFor(
    StudentExamQuestion question,
    String field,
  ) {
    final key = '${question.id}:$field';
    return _answerControllers.putIfAbsent(
      key,
      () => TextEditingController(
        text: field == 'value'
            ? (question.answer.values.isEmpty
                  ? ''
                  : question.answer.values.first)
            : question.answer[field] ?? '',
      ),
    );
  }

  void _clearAnswerControllers() {
    for (final controller in _answerControllers.values) {
      controller.dispose();
    }
    _answerControllers.clear();
  }

  void _showMessage(String message, {bool error = false}) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          backgroundColor: error ? Theme.of(context).colorScheme.error : null,
          content: Text(message),
        ),
      );
  }
}

class _SafeModeBanner extends StatelessWidget {
  const _SafeModeBanner({required this.security, this.locked = false});

  final StudentExamSecurity security;
  final bool locked;

  @override
  Widget build(BuildContext context) {
    final color = locked ? const Color(0xFFC0392B) : NusaColors.primary;
    final strict = security.trackAppSwitch && security.action == 'tahan';
    return Container(
      key: const Key('student-exam-safe-mode'),
      margin: locked
          ? EdgeInsets.zero
          : const EdgeInsets.fromLTRB(12, 8, 12, 0),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Row(
        children: [
          Icon(
            locked ? Icons.lock_clock_rounded : Icons.verified_user_rounded,
            size: 19,
            color: color,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              locked
                  ? security.holdReason == 'peninjauan_sesi'
                        ? 'Menunggu pemeriksaan sesi · bukan penetapan kecurangan'
                        : security.holdReason == 'keluar_pengerjaan'
                        ? 'Sesi ditahan setelah Simpan & Keluar'
                        : 'Mode Aman menahan ujian · ${security.incidentCount} kejadian · ${security.totalAwaySeconds} detik di luar NUSA'
                  : strict
                  ? 'Mode Aman aktif${security.graceSeconds == 0 ? ' · Nol toleransi' : ''} · Peringatan ${security.incidentCount}/${security.incidentLimit}'
                  : 'Mode Aman aktif · Aktivitas keluar aplikasi dicatat',
              style: TextStyle(
                color: color,
                fontSize: 10.5,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ExamStatusBar extends StatelessWidget {
  const _ExamStatusBar({
    required this.subject,
    required this.current,
    required this.total,
    required this.progress,
    required this.onNavigate,
  });
  final String subject;
  final int current;
  final int total;
  final StudentExamProgress progress;
  final VoidCallback onNavigate;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(16, 8, 16, 11),
    decoration: const BoxDecoration(
      color: Colors.white,
      border: Border(bottom: BorderSide(color: NusaColors.outline)),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                subject,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w900),
              ),
              Text(
                '${progress.answered} terjawab · ${progress.doubtful} ragu-ragu',
                style: const TextStyle(
                  color: NusaColors.textSecondary,
                  fontSize: 10.5,
                ),
              ),
            ],
          ),
        ),
        TextButton.icon(
          key: const Key('student-exam-navigator'),
          onPressed: onNavigate,
          icon: const Icon(Icons.grid_view_rounded, size: 18),
          label: Text('$current / $total'),
        ),
      ],
    ),
  );
}

class _ExamNavigationBar extends StatelessWidget {
  const _ExamNavigationBar({
    required this.current,
    required this.total,
    required this.finishing,
    required this.canFinish,
    required this.finishAvailability,
    required this.saveStatus,
    required this.onPrevious,
    required this.onNext,
    required this.onFinish,
  });
  final int current;
  final int total;
  final bool finishing;
  final bool canFinish;
  final String finishAvailability;
  final _AnswerSaveStatus saveStatus;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  final VoidCallback onFinish;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.fromLTRB(
      12,
      9,
      12,
      9 + MediaQuery.paddingOf(context).bottom,
    ),
    decoration: BoxDecoration(
      color: Colors.white,
      border: const Border(top: BorderSide(color: NusaColors.outline)),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.06),
          blurRadius: 16,
          offset: const Offset(0, -4),
        ),
      ],
    ),
    child: Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            IconButton.filledTonal(
              tooltip: 'Sebelumnya',
              onPressed: onPrevious,
              icon: const Icon(Icons.arrow_back_rounded),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _SaveStatusLabel(status: saveStatus, centered: true),
            ),
            const SizedBox(width: 8),
            if (onNext != null)
              FilledButton.icon(
                key: const Key('student-exam-next'),
                style: FilledButton.styleFrom(minimumSize: const Size(0, 48)),
                onPressed: onNext,
                icon: const Icon(Icons.arrow_forward_rounded),
                label: const Text('Berikutnya'),
              )
            else
              const SizedBox(width: 48),
          ],
        ),
        if (onNext == null) ...[
          const SizedBox(height: 9),
          const Divider(height: 1),
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerLeft,
            child: Text(
              finishAvailability,
              style: const TextStyle(
                color: NusaColors.textSecondary,
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          const SizedBox(height: 7),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              key: const Key('student-exam-finish'),
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                backgroundColor: const Color(0xFF9F2F2F),
              ),
              onPressed: finishing || !canFinish ? null : onFinish,
              icon: finishing
                  ? const SizedBox.square(
                      dimension: 17,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Icon(Icons.task_alt_rounded),
              label: const Text('Kumpulkan Ujian'),
            ),
          ),
        ],
      ],
    ),
  );
}

class _TimerPill extends StatelessWidget {
  const _TimerPill({required this.seconds});
  final int seconds;

  @override
  Widget build(BuildContext context) {
    final urgent = seconds <= 300;
    return Container(
      key: const Key('student-exam-timer'),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: urgent ? const Color(0xFFFFECEA) : const Color(0xFFFFF8D7),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: urgent ? const Color(0xFFE36A5D) : NusaColors.accent,
        ),
      ),
      child: Text(
        _duration(seconds),
        style: TextStyle(
          color: urgent ? const Color(0xFFAA2E25) : NusaColors.primaryDark,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SaveStatusLabel extends StatelessWidget {
  const _SaveStatusLabel({required this.status, this.centered = false});
  final _AnswerSaveStatus status;
  final bool centered;

  @override
  Widget build(BuildContext context) {
    final (icon, label, color) = switch (status) {
      _AnswerSaveStatus.pending => (
        Icons.cloud_upload_outlined,
        'Belum tersimpan',
        const Color(0xFFE59A00),
      ),
      _AnswerSaveStatus.localSaved => (
        Icons.phone_android_rounded,
        'Draft di HP',
        const Color(0xFFE59A00),
      ),
      _AnswerSaveStatus.saving => (
        Icons.sync_rounded,
        'Menyimpan...',
        NusaColors.primary,
      ),
      _AnswerSaveStatus.saved => (
        Icons.cloud_done_rounded,
        'Tersimpan',
        NusaColors.success,
      ),
      _AnswerSaveStatus.failed => (
        Icons.cloud_off_rounded,
        'Draft di HP',
        const Color(0xFFC0392B),
      ),
    };
    return LayoutBuilder(
      builder: (context, constraints) {
        final iconOnly = centered && constraints.maxWidth < 64;
        return Tooltip(
          message:
              status == _AnswerSaveStatus.localSaved ||
                  status == _AnswerSaveStatus.failed
              ? 'Draft tersimpan di HP, belum tersimpan di server'
              : label,
          child: Row(
            mainAxisSize: centered ? MainAxisSize.max : MainAxisSize.min,
            mainAxisAlignment: centered
                ? MainAxisAlignment.center
                : MainAxisAlignment.start,
            children: [
              Icon(icon, size: 15, color: color),
              if (!iconOnly) ...[
                const SizedBox(width: 4),
                Flexible(
                  child: Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: color,
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }
}

class _ExamHero extends StatelessWidget {
  const _ExamHero({required this.exam, required this.participant});
  final StudentExamInfo exam;
  final StudentExamParticipant participant;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [NusaColors.primary, NusaColors.primaryDark],
      ),
      borderRadius: BorderRadius.circular(20),
      boxShadow: [
        BoxShadow(
          color: NusaColors.primary.withValues(alpha: 0.18),
          blurRadius: 18,
          offset: const Offset(0, 8),
        ),
      ],
    ),
    child: Row(
      children: [
        Container(
          width: 52,
          height: 52,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(15),
            border: Border.all(color: NusaColors.accent),
          ),
          child: const Icon(Icons.quiz_rounded, color: NusaColors.accent),
        ),
        const SizedBox(width: 13),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                exam.subject,
                style: const TextStyle(
                  color: NusaColors.accent,
                  fontSize: 11,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: 3),
              Text(
                exam.name,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                '${participant.name} · ${participant.schoolClass}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.78),
                  fontSize: 11,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _ProgressCard extends StatelessWidget {
  const _ProgressCard({required this.progress});
  final StudentExamProgress progress;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 13),
      child: Row(
        children: [
          _metric('Soal', progress.questionCount, NusaColors.primary),
          _metric('Terjawab', progress.answered, NusaColors.success),
          _metric('Belum', progress.unanswered, const Color(0xFFE59A00)),
          _metric('Ragu', progress.doubtful, const Color(0xFFC08600)),
        ],
      ),
    ),
  );

  Widget _metric(String label, int value, Color color) => Expanded(
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
        Text(
          label,
          style: const TextStyle(
            color: NusaColors.textSecondary,
            fontSize: 9.5,
          ),
        ),
      ],
    ),
  );
}

class _InformationCard extends StatelessWidget {
  const _InformationCard({required this.session});
  final StudentExamSession session;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Informasi Ujian',
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          _InfoRow(
            icon: Icons.badge_outlined,
            label: 'Nomor peserta',
            value: session.participant.participantNumber ?? '-',
          ),
          _InfoRow(
            icon: Icons.schedule_rounded,
            label: 'Durasi',
            value: '${session.exam.durationMinutes} menit',
          ),
          _InfoRow(
            icon: Icons.meeting_room_outlined,
            label: 'Ruang / meja',
            value:
                [
                      session.participant.room,
                      if (session.participant.seatNumber != null)
                        'Meja ${session.participant.seatNumber}',
                    ]
                    .whereType<String>()
                    .where((value) => value.isNotEmpty)
                    .join(' · ')
                    .ifEmpty('-'),
          ),
          _InfoRow(
            icon: Icons.security_rounded,
            label: 'Keamanan',
            value: session.exam.singleDevice
                ? 'Dibatasi satu perangkat'
                : 'Perangkat tidak dibatasi',
            last: true,
          ),
        ],
      ),
    ),
  );
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({
    required this.icon,
    required this.label,
    required this.value,
    this.last = false,
  });
  final IconData icon;
  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.only(bottom: last ? 0 : 11),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, size: 18, color: NusaColors.primary),
        const SizedBox(width: 9),
        SizedBox(
          width: 94,
          child: Text(
            label,
            style: const TextStyle(
              color: NusaColors.textSecondary,
              fontSize: 11,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
          ),
        ),
      ],
    ),
  );
}

class _InstructionCard extends StatelessWidget {
  const _InstructionCard({this.instructions});
  final String? instructions;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Icon(Icons.menu_book_rounded, color: NusaColors.primary),
              SizedBox(width: 8),
              Text(
                'Petunjuk Ujian',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            instructions?.trim().isNotEmpty == true ? instructions! : 'Baca setiap soal dengan teliti, kerjakan secara jujur, dan pastikan jawaban tersimpan sebelum selesai.',
            style: const TextStyle(
              color: NusaColors.textSecondary,
              height: 1.45,
            ),
          ),
        ],
      ),
    ),
  );
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({this.result});
  final StudentExamResult? result;

  @override
  Widget build(BuildContext context) {
    final value = result;
    if (value?.visible == true && value?.score != null) {
      final passed = value?.passed;
      return Card(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            children: [
              const Text(
                'Nilai Ujian',
                style: TextStyle(
                  color: NusaColors.textSecondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                _number(value!.score!),
                style: const TextStyle(
                  color: NusaColors.primary,
                  fontSize: 38,
                  fontWeight: FontWeight.w900,
                ),
              ),
              if (passed != null)
                Text(
                  passed ? 'Tuntas' : 'Belum tuntas',
                  style: TextStyle(
                    color: passed
                        ? NusaColors.success
                        : const Color(0xFFC0392B),
                    fontWeight: FontWeight.w900,
                  ),
                ),
            ],
          ),
        ),
      );
    }
    return _Notice(
      message: value?.awaitingCorrection == true
          ? 'Nilai akan tampil setelah guru menyelesaikan koreksi jawaban uraian.'
          : 'Nilai tidak ditampilkan untuk ujian ini. Informasi hasil diberikan oleh guru.',
    );
  }
}

class _CompletionIcon extends StatelessWidget {
  const _CompletionIcon();

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 92,
      height: 92,
      decoration: BoxDecoration(
        color: NusaColors.successSurface,
        shape: BoxShape.circle,
        border: Border.all(
          color: NusaColors.success.withValues(alpha: 0.35),
          width: 2,
        ),
      ),
      child: const Icon(
        Icons.task_alt_rounded,
        color: NusaColors.success,
        size: 50,
      ),
    ),
  );
}

class _Notice extends StatelessWidget {
  const _Notice({required this.message, this.warning = false});
  final String message;
  final bool warning;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(13),
    decoration: BoxDecoration(
      color: warning ? const Color(0xFFFFF8D7) : NusaColors.surfaceBlue,
      borderRadius: BorderRadius.circular(13),
      border: Border.all(
        color: warning
            ? NusaColors.accent.withValues(alpha: 0.7)
            : NusaColors.outline,
      ),
    ),
    child: Text(message, style: const TextStyle(fontSize: 11.5, height: 1.4)),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        children: [
          const Icon(
            Icons.cloud_off_rounded,
            size: 52,
            color: NusaColors.textSecondary,
          ),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: 14),
            FilledButton.tonalIcon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Coba Lagi'),
            ),
          ],
        ],
      ),
    ),
  );
}

enum _AnswerSaveStatus { pending, localSaved, saving, saved, failed }

String _message(Object error) =>
    error is AppException ? error.message : 'Terjadi gangguan pada ujian.';

String _duration(int seconds) {
  final hours = seconds ~/ 3600;
  final minutes = (seconds % 3600) ~/ 60;
  final remainingSeconds = seconds % 60;
  return [
    hours,
    minutes,
    remainingSeconds,
  ].map((value) => value.toString().padLeft(2, '0')).join(':');
}

String _number(double value) => value == value.roundToDouble()
    ? value.toInt().toString()
    : value.toStringAsFixed(2).replaceAll('.', ',');

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}
