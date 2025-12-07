// assets/sounds/generate-pling.js
// Small utility to generate a short "pling" WAV in the browser and return an object URL.
// Usage example:
//   const url = generatePlingWavUrl(880, 0.12); // frequency Hz, duration seconds
//   const a = new Audio(url); a.play();
//   // If you want to persist the URL, createObjectURL returns a blob url; to free it later call URL.revokeObjectURL(url)

function generatePlingWavUrl(frequency, durationSeconds, sampleRate) {
  frequency = frequency || 880;
  durationSeconds = typeof durationSeconds === 'number' ? durationSeconds : 0.12;
  sampleRate = sampleRate || 44100;

  var numSamples = Math.floor(sampleRate * durationSeconds);
  var bytesPerSample = 2; // 16-bit PCM
  var blockAlign = bytesPerSample * 1; // mono

  // PCM buffer size = header (44) + samples * bytesPerSample
  var buffer = new ArrayBuffer(44 + numSamples * bytesPerSample);
  var view = new DataView(buffer);

  // Write WAV header
  function writeString(view, offset, string) {
    for (var i = 0; i < string.length; i++) {
      view.setUint8(offset + i, string.charCodeAt(i));
    }
  }

  writeString(view, 0, 'RIFF');
  view.setUint32(4, 36 + numSamples * bytesPerSample, true); // file length - 8
  writeString(view, 8, 'WAVE');
  writeString(view, 12, 'fmt ');
  view.setUint32(16, 16, true); // PCM chunk size
  view.setUint16(20, 1, true); // PCM format
  view.setUint16(22, 1, true); // channels
  view.setUint32(24, sampleRate, true); // sample rate
  view.setUint32(28, sampleRate * blockAlign, true); // byte rate
  view.setUint16(32, blockAlign, true); // block align
  view.setUint16(34, bytesPerSample * 8, true); // bits per sample
  writeString(view, 36, 'data');
  view.setUint32(40, numSamples * bytesPerSample, true);

  // Fill PCM samples (sine wave with quick envelope to avoid click)
  var maxAmp = 0.8; // amplitude
  for (var i = 0; i < numSamples; i++) {
    var t = i / sampleRate;
    // simple envelope: attack 5ms, release last 20ms
    var env = 1.0;
    var attack = 0.005;
    var release = 0.02;
    if (t < attack) env = t / attack;
    else if (t > durationSeconds - release) env = Math.max(0, (durationSeconds - t) / release);

    var sample =
      Math.sin(2 * Math.PI * frequency * t) *
      maxAmp *
      env *
      (0.5 + 0.5 * Math.sin(2 * Math.PI * 2 * t)); // a little modulation for richer tone

    // Clamp and convert to 16-bit PCM
    var s = Math.max(-1, Math.min(1, sample));
    var intSample = Math.floor(s * 32767);
    view.setInt16(44 + i * bytesPerSample, intSample, true);
  }

  var blob = new Blob([view], { type: 'audio/wav' });
  return URL.createObjectURL(blob);
}

// Optional convenience: directly play the pling
function playPling(frequency, durationSeconds) {
  var url = generatePlingWavUrl(frequency, durationSeconds);
  var a = new Audio(url);
  // try to play; user gesture may be required by some browsers
  var p = a.play();
  if (p && p.catch) {
    p.catch(function () {
      // Could not autoplay; user gesture required.
    });
  }
  // revoke url after a short timeout to free memory
  setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
  return a;
}