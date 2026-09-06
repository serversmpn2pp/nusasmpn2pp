import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:nusa/core/theme/app_theme.dart';

class InventoryBarcodeScannerSheet extends StatefulWidget {
  const InventoryBarcodeScannerSheet({
    required this.title,
    required this.guide,
    super.key,
  });
  final String title;
  final String guide;

  @override
  State<InventoryBarcodeScannerSheet> createState() =>
      _InventoryBarcodeScannerSheetState();
}

class _InventoryBarcodeScannerSheetState
    extends State<InventoryBarcodeScannerSheet> {
  bool _finished = false;
  late final MobileScannerController _controller = MobileScannerController(
    facing: CameraFacing.back,
    formats: const [
      BarcodeFormat.qrCode,
      BarcodeFormat.code128,
      BarcodeFormat.code39,
      BarcodeFormat.code93,
      BarcodeFormat.ean13,
      BarcodeFormat.ean8,
    ],
    detectionSpeed: DetectionSpeed.noDuplicates,
    autoZoom: true,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    height: (MediaQuery.sizeOf(context).height * 0.82).clamp(480.0, 760.0),
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 8, 8),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  widget.title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              IconButton(
                tooltip: 'Tutup',
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close_rounded),
              ),
            ],
          ),
        ),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  MobileScanner(
                    key: const Key('inventory-barcode-scanner'),
                    controller: _controller,
                    tapToFocus: true,
                    onDetect: (capture) {
                      if (_finished) return;
                      for (final barcode in capture.barcodes) {
                        final value = barcode.rawValue?.trim();
                        if (value != null && value.isNotEmpty) {
                          _finished = true;
                          Navigator.pop(context, value);
                          break;
                        }
                      }
                    },
                    errorBuilder: (context, error) => ColoredBox(
                      color: NusaColors.primaryDark,
                      child: Center(
                        child: Padding(
                          padding: const EdgeInsets.all(24),
                          child: Text(
                            error.errorCode ==
                                    MobileScannerErrorCode.permissionDenied
                                ? 'Izin kamera belum diberikan. Gunakan input kode manual.'
                                : 'Kamera tidak tersedia. Gunakan input kode manual.',
                            textAlign: TextAlign.center,
                            style: const TextStyle(color: Colors.white),
                          ),
                        ),
                      ),
                    ),
                  ),
                  Center(
                    child: FractionallySizedBox(
                      widthFactor: 0.76,
                      heightFactor: 0.42,
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          border: Border.all(
                            color: NusaColors.accent,
                            width: 3,
                          ),
                          borderRadius: BorderRadius.circular(18),
                        ),
                      ),
                    ),
                  ),
                  Align(
                    alignment: Alignment.bottomCenter,
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.fromLTRB(16, 38, 16, 16),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          begin: Alignment.topCenter,
                          end: Alignment.bottomCenter,
                          colors: [
                            Colors.transparent,
                            Colors.black.withValues(alpha: 0.78),
                          ],
                        ),
                      ),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            widget.guide,
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 10),
                          ValueListenableBuilder<MobileScannerState>(
                            valueListenable: _controller,
                            builder: (context, state, child) =>
                                IconButton.filled(
                                  tooltip: 'Lampu',
                                  onPressed:
                                      state.torchState == TorchState.unavailable
                                      ? null
                                      : _controller.toggleTorch,
                                  icon: Icon(
                                    state.torchState == TorchState.on
                                        ? Icons.flash_on_rounded
                                        : Icons.flash_off_rounded,
                                  ),
                                ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    ),
  );
}
