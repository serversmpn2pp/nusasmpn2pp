import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:nusa/core/theme/app_theme.dart';

class ExamAttendanceCamera extends StatefulWidget {
  const ExamAttendanceCamera({
    required this.onDetected,
    required this.processing,
    super.key,
  });

  final ValueChanged<String> onDetected;
  final bool processing;

  @override
  State<ExamAttendanceCamera> createState() => _ExamAttendanceCameraState();
}

class _ExamAttendanceCameraState extends State<ExamAttendanceCamera> {
  late final MobileScannerController _controller = MobileScannerController(
    facing: CameraFacing.back,
    formats: const [BarcodeFormat.qrCode],
    detectionSpeed: DetectionSpeed.normal,
    detectionTimeoutMs: 650,
    autoZoom: true,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(20),
    child: AspectRatio(
      aspectRatio: 1.08,
      child: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            key: const Key('exam-attendance-mobile-scanner'),
            controller: _controller,
            tapToFocus: true,
            onDetect: (capture) {
              if (widget.processing) return;
              for (final barcode in capture.barcodes) {
                final value = barcode.rawValue?.trim();
                if (value != null && value.isNotEmpty) {
                  widget.onDetected(value);
                  break;
                }
              }
            },
            errorBuilder: (context, error) => _CameraError(error: error),
            placeholderBuilder: (context) => const ColoredBox(
              color: NusaColors.primaryDark,
              child: Center(child: CircularProgressIndicator()),
            ),
          ),
          const IgnorePointer(child: _ScanFrame()),
          Align(
            alignment: Alignment.bottomCenter,
            child: Container(
              padding: const EdgeInsets.fromLTRB(14, 28, 14, 12),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.transparent,
                    Colors.black.withValues(alpha: 0.72),
                  ],
                ),
              ),
              child: ValueListenableBuilder<MobileScannerState>(
                valueListenable: _controller,
                builder: (context, state, child) => Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    IconButton.filled(
                      tooltip: state.torchState == TorchState.on
                          ? 'Matikan lampu'
                          : 'Nyalakan lampu',
                      onPressed: state.torchState == TorchState.unavailable
                          ? null
                          : _controller.toggleTorch,
                      style: IconButton.styleFrom(
                        backgroundColor: state.torchState == TorchState.on
                            ? NusaColors.accent
                            : Colors.white24,
                        foregroundColor: state.torchState == TorchState.on
                            ? NusaColors.primaryDark
                            : Colors.white,
                      ),
                      icon: Icon(
                        state.torchState == TorchState.on
                            ? Icons.flash_on_rounded
                            : Icons.flash_off_rounded,
                      ),
                    ),
                    const SizedBox(width: 14),
                    IconButton.filled(
                      tooltip: 'Ganti kamera',
                      onPressed: (state.availableCameras ?? 0) > 1
                          ? _controller.switchCamera
                          : null,
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white24,
                        foregroundColor: Colors.white,
                      ),
                      icon: const Icon(Icons.cameraswitch_rounded),
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (widget.processing)
            ColoredBox(
              color: Colors.black.withValues(alpha: 0.52),
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(),
                    SizedBox(height: 12),
                    Text(
                      'Memeriksa peserta dan ruang...',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    ),
  );
}

class _ScanFrame extends StatelessWidget {
  const _ScanFrame();

  @override
  Widget build(BuildContext context) => Center(
    child: FractionallySizedBox(
      widthFactor: 0.68,
      heightFactor: 0.68,
      child: DecoratedBox(
        decoration: BoxDecoration(
          border: Border.all(color: NusaColors.accent, width: 3),
          borderRadius: BorderRadius.circular(22),
          boxShadow: [
            BoxShadow(
              color: NusaColors.accent.withValues(alpha: 0.2),
              blurRadius: 14,
              spreadRadius: 1,
            ),
          ],
        ),
        child: const Center(
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 16),
            child: Text(
              'Arahkan QR kartu pelajar ke dalam kotak',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w800,
                shadows: [Shadow(color: Colors.black, blurRadius: 5)],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class _CameraError extends StatelessWidget {
  const _CameraError({required this.error});

  final MobileScannerException error;

  @override
  Widget build(BuildContext context) {
    final denied = error.errorCode == MobileScannerErrorCode.permissionDenied;
    final unsupported = error.errorCode == MobileScannerErrorCode.unsupported;
    final message = denied
        ? 'Izin kamera ditolak. Aktifkan izin kamera NUSA melalui Pengaturan Android.'
        : unsupported
        ? 'Kamera tidak tersedia pada perangkat ini.'
        : 'Kamera belum dapat dijalankan. Tutup halaman lalu coba kembali.';

    return ColoredBox(
      color: NusaColors.primaryDark,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.no_photography_rounded,
                color: NusaColors.accent,
                size: 44,
              ),
              const SizedBox(height: 12),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white, height: 1.4),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
