import 'package:flutter/material.dart';
import 'package:nusa/core/theme/app_theme.dart';
import 'package:nusa/features/student_exam/domain/student_exam.dart';

/// In-app zoom keeps exam lifecycle and FLAG_SECURE protection in place.
class ExamQuestionImage extends StatefulWidget {
  const ExamQuestionImage({required this.image, super.key});
  final StudentExamImage image;
  @override
  State<ExamQuestionImage> createState() => _ExamQuestionImageState();
}

class _ExamQuestionImageState extends State<ExamQuestionImage> {
  int _attempt = 0;
  DialogRoute<void>? _zoomRoute;

  @override
  void dispose() {
    final route = _zoomRoute;
    if (route != null) {
      // A hold/deadline can remove the question while its image is enlarged.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (route.isActive) route.navigator?.removeRoute(route);
      });
    }
    super.dispose();
  }

  Future<void> _retry() async {
    await NetworkImage(widget.image.url).evict();
    if (mounted) setState(() => _attempt++);
  }

  Widget _picture({
    bool enlarged = false,
    VoidCallback? onRetry,
  }) => Image.network(
    widget.image.url,
    key: ValueKey('${widget.image.url}:$_attempt:$enlarged'),
    width: enlarged ? null : double.infinity,
    fit: BoxFit.contain,
    filterQuality: FilterQuality.high,
    semanticLabel: widget.image.alt ?? 'Gambar soal',
    loadingBuilder: (context, child, progress) => progress == null
        ? child
        : const SizedBox(
            height: 140,
            child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
          ),
    errorBuilder: (context, error, stack) => Container(
      padding: const EdgeInsets.all(14),
      color: NusaColors.surfaceBlue,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.broken_image_outlined, color: NusaColors.primary),
          Text(
            'Gambar soal belum dapat dimuat${error is NetworkImageLoadException ? ' (HTTP ${error.statusCode})' : ''}.',
            textAlign: TextAlign.center,
          ),
          TextButton.icon(
            onPressed: onRetry ?? _retry,
            icon: const Icon(Icons.refresh),
            label: const Text('Muat Ulang Gambar'),
          ),
        ],
      ),
    ),
  );
  Future<void> _enlarge() async {
    if (_zoomRoute != null) return;
    final navigator = Navigator.of(context, rootNavigator: true);
    final route = DialogRoute<void>(
      context: context,
      themes: InheritedTheme.capture(from: context, to: navigator.context),
      builder: _zoomDialog,
    );
    _zoomRoute = route;
    try {
      await navigator.push(route);
    } finally {
      if (identical(_zoomRoute, route)) _zoomRoute = null;
    }
  }

  Widget _zoomDialog(BuildContext context) => StatefulBuilder(
    builder: (context, refreshDialog) => Dialog.fullscreen(
      child: Scaffold(
        backgroundColor: Colors.white,
        appBar: AppBar(
          title: const Text('Gambar Soal'),
          leading: IconButton(
            tooltip: 'Tutup gambar',
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.close),
          ),
        ),
        body: SafeArea(
          child: Column(
            children: [
              const Padding(
                padding: EdgeInsets.all(12),
                child: Text('Cubit untuk memperbesar, lalu geser gambar.'),
              ),
              Expanded(
                child: InteractiveViewer(
                  key: const Key('exam-question-image-viewer'),
                  minScale: 1,
                  maxScale: 6,
                  boundaryMargin: const EdgeInsets.all(24),
                  child: Center(
                    child: _picture(
                      enlarged: true,
                      onRetry: () async {
                        await _retry();
                        if (context.mounted) refreshDialog(() {});
                      },
                    ),
                  ),
                ),
              ),
              if (widget.image.caption?.trim().isNotEmpty == true)
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    widget.image.caption!,
                    textAlign: TextAlign.center,
                  ),
                ),
            ],
          ),
        ),
      ),
    ),
  );
  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        clipBehavior: Clip.antiAlias,
        child: InkWell(onTap: _enlarge, child: _picture()),
      ),
      Align(
        alignment: Alignment.centerRight,
        child: TextButton.icon(
          onPressed: _enlarge,
          icon: const Icon(Icons.zoom_in, size: 18),
          label: const Text('Perbesar Gambar'),
        ),
      ),
    ],
  );
}
