import AppKit
import CoreGraphics
import Foundation

// Reframes the existing Aabhushan monogram for the web install icon.
// Android launcher assets are intentionally outside this script.
let project = URL(fileURLWithPath: CommandLine.arguments[0]).deletingLastPathComponent().deletingLastPathComponent()
let sourceURL = project.appendingPathComponent("tool/artwork/aabhushan_mark_source.png")
let source = NSBitmapImageRep(data: try Data(contentsOf: sourceURL))!
var minX = source.pixelsWide
var minY = source.pixelsHigh
var maxX = 0
var maxY = 0
for y in 0..<source.pixelsHigh {
    for x in 0..<source.pixelsWide {
        if (source.colorAt(x: x, y: y)?.alphaComponent ?? 0) > 0.05 {
            minX = min(minX, x)
            minY = min(minY, y)
            maxX = max(maxX, x)
            maxY = max(maxY, y)
        }
    }
}
let crop = CGRect(x: max(0, minX - 7), y: max(0, minY - 7), width: maxX - minX + 15, height: maxY - minY + 15)
let mark = source.cgImage!.cropping(to: crop)!
let markURL = project.appendingPathComponent("assets/images/brand/aabhushan_mark.png")
try NSBitmapImageRep(cgImage: mark).representation(using: .png, properties: [:])!.write(to: markURL)

func color(_ red: CGFloat, _ green: CGFloat, _ blue: CGFloat) -> CGColor {
    CGColor(colorSpace: CGColorSpaceCreateDeviceRGB(), components: [red / 255, green / 255, blue / 255, 1])!
}

func render(size: Int, maskable: Bool, output: String) throws {
    let space = CGColorSpaceCreateDeviceRGB()
    let context = CGContext(data: nil, width: size, height: size, bitsPerComponent: 8, bytesPerRow: 0, space: space, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
    let side = CGFloat(size)
    let gradient = CGGradient(colorsSpace: space, colors: [color(55, 23, 42), color(111, 37, 50)] as CFArray, locations: [0, 1])!
    context.drawLinearGradient(gradient, start: CGPoint(x: 0, y: side), end: CGPoint(x: side, y: 0), options: [])

    let outer = side * (maskable ? 0.37 : 0.40)
    let center = CGPoint(x: side / 2, y: side / 2)
    context.setStrokeColor(color(198, 154, 66))
    context.setLineWidth(side * 0.006)
    context.strokeEllipse(in: CGRect(x: center.x - outer - side * 0.025, y: center.y - outer - side * 0.025, width: (outer + side * 0.025) * 2, height: (outer + side * 0.025) * 2))
    context.setFillColor(color(255, 251, 244))
    context.fillEllipse(in: CGRect(x: center.x - outer, y: center.y - outer, width: outer * 2, height: outer * 2))
    context.setStrokeColor(color(226, 202, 155))
    context.setLineWidth(side * 0.008)
    context.strokeEllipse(in: CGRect(x: center.x - outer + side * 0.01, y: center.y - outer + side * 0.01, width: outer * 2 - side * 0.02, height: outer * 2 - side * 0.02))

    let markWidth = side * (maskable ? 0.53 : 0.59)
    let markHeight = markWidth * CGFloat(mark.height) / CGFloat(mark.width)
    let markRect = CGRect(x: (side - markWidth) / 2, y: (side - markHeight) / 2 + side * 0.004, width: markWidth, height: markHeight)
    context.draw(mark, in: markRect)

    let image = context.makeImage()!
    let url = project.appendingPathComponent(output)
    try NSBitmapImageRep(cgImage: image).representation(using: .png, properties: [:])!.write(to: url)
}

try render(size: 512, maskable: false, output: "web/icons/Icon-512.png")
try render(size: 192, maskable: false, output: "web/icons/Icon-192.png")
try render(size: 512, maskable: true, output: "web/icons/Icon-maskable-512.png")
try render(size: 192, maskable: true, output: "web/icons/Icon-maskable-192.png")
try render(size: 64, maskable: false, output: "web/favicon.png")
