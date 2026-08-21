/**
 * NEO // MATRIX SENTIENT HERO - INTERACTIVE CONTROLLER ENGINE
 * 
 * Features:
 * 1. Interactive Tech-Pill Controller: Click pills to trigger instant 3D state transitions
 * 2. Real-time Two-Way UI Sync: Pill buttons highlight dynamically with the state machine
 * 3. 3-State Seamless Loop:
 *    - State 0: Electrical Internet Waves (Ondas de Rede)
 *    - State 1: Matrix Digital Rain Cascade (Fluxo Matrix)
 *    - State 2: 3D Photographic Face of Neo (Consciência 3D com Óculos Matrix)
 * 4. Quintic C2 Smoothstep: S(t) = 6t^5 - 15t^4 + 10t^3 (Zero snap, zero jerk)
 * 5. Mobile-First 320px Responsive Viewport & Touch Parallax
 * 6. Two-Way Scroll Context Alpha & GPU Optimization
 */

class InteractiveHeroParticleEngine {
  constructor(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d');

    this.particles = [];
    this.rainParticles = [];
    this.ambientNodes = [];
    this.techPillButtons = [];

    // 3D Camera & Transform
    this.rotY = 0;
    this.rotX = 0;
    this.targetRotY = 0;
    this.targetRotX = 0;
    this.cameraDistance = 750;

    // Viewport Placement
    this.headScreenX = window.innerWidth * 0.50;
    this.headScreenY = window.innerHeight * 0.40;
    this.baseHeadX = this.headScreenX;
    this.baseHeadY = this.headScreenY;
    this.scaleFactor = 1.25;

    // Scroll & Bounds State
    this.scrollY = 0;
    this.targetScrollY = 0;
    this.smoothScrollY = 0;
    this.scrollVelocity = 0;
    this.fadeAlpha = 1.0;
    this.statsBanner = null;

    // 3-State Machine Loop (0: Waves, 1: Rain, 2: Face)
    this.morphState = 0;
    this.morphFromState = 0;
    this.morphToState = 1;
    this.isTransitioning = false;
    this.morphProgress = 0;
    this.stateTimer = 0;

    this.holdDurationWaves = 5.5;
    this.holdDurationRain = 4.5;
    this.holdDurationFace = 5.5;
    this.transitionDuration = 2.0;

    // State 1: Matrix Rain Columns
    this.streamCols = 64;
    this.columnHeads = new Float32Array(this.streamCols);
    this.columnSpeeds = new Float32Array(this.streamCols);
    for (let c = 0; c < this.streamCols; c++) {
      this.columnHeads[c] = Math.random() * 200;
      this.columnSpeeds[c] = 75 + Math.random() * 95;
    }

    // State 0: Electrical Network Waves
    this.waveStrands = 28;

    // Pointer Interaction
    this.mouse = { x: -1000, y: -1000, active: false };
    this.lastTime = 0;
    this.animId = null;
    this.time = 0;

    this.init();
  }

  init() {
    this.handleResize = this.resize.bind(this);
    window.addEventListener('resize', this.handleResize);

    this.handleScroll = () => {
      this.targetScrollY = window.scrollY || window.pageYOffset || 0;
    };
    window.addEventListener('scroll', this.handleScroll, { passive: true });

    this.statsBanner = document.querySelector('.stats-banner');

    this.resize();
    this.setupPointer();
    this.setupControllerPills();
    this.initAmbientAtmosphere();
    this.buildParticles();
    this.start();
  }

  resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.width = window.innerWidth;
    this.height = window.innerHeight;

    this.canvas.width = Math.floor(this.width * dpr);
    this.canvas.height = Math.floor(this.height * dpr);
    this.canvas.style.width = `${this.width}px`;
    this.canvas.style.height = `${this.height}px`;

    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    const isSmallMobile = this.width <= 480;
    const isTablet = this.width < 1080;

    this.baseHeadX = isTablet ? this.width * 0.50 : this.width * 0.72;
    this.baseHeadY = isSmallMobile 
      ? this.height * 0.35 
      : (isTablet ? this.height * 0.44 : this.height * 0.50);

    this.headScreenX = this.baseHeadX;
    this.headScreenY = this.baseHeadY;

    if (isSmallMobile) {
      this.scaleFactor = Math.min(Math.max((this.width / 320) * 1.15, 1.05), 1.45);
    } else if (isTablet) {
      this.scaleFactor = Math.min(Math.max(this.width / 420, 1.6), 2.0);
    } else {
      this.scaleFactor = Math.min(Math.max(this.height / 360, 2.3), 3.0);
    }
  }

  setupControllerPills() {
    this.techPillButtons = Array.from(document.querySelectorAll('.hero-tech-pills .tech-pill'));

    this.techPillButtons.forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const targetState = parseInt(btn.getAttribute('data-state'), 10);
        if (!isNaN(targetState) && targetState >= 0 && targetState <= 2) {
          this.switchState(targetState);
        }
      });
    });

    this.updatePillUI(this.morphState);
  }

  switchState(targetStateIndex) {
    if (this.morphState === targetStateIndex && !this.isTransitioning) {
      this.stateTimer = 0;
      return;
    }

    this.morphFromState = this.morphState;
    this.morphToState = targetStateIndex;
    this.isTransitioning = true;
    this.morphProgress = 0;
    this.stateTimer = 0;
    this.triggerVortexImpulse(200);

    this.updatePillUI(targetStateIndex);
  }

  updatePillUI(activeStateIndex) {
    if (!this.techPillButtons || this.techPillButtons.length === 0) return;

    this.techPillButtons.forEach((btn, index) => {
      const isActive = index === activeStateIndex;
      btn.classList.toggle('active', isActive);
      btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    const coordReadout = document.getElementById('coord-readout');
    if (coordReadout) {
      const stateNames = ['ONDAS_DE_REDE', 'FLUXO_MATRIX', 'CONSCIENCIA_3D'];
      coordReadout.textContent = `X: ${(this.targetRotY * 45).toFixed(1)}° | Y: ${(this.targetRotX * 45).toFixed(1)}° | ESTADO: ${stateNames[activeStateIndex]}`;
    }
  }

  setupPointer() {
    const onPointerAction = (clientX, clientY) => {
      this.mouse.x = clientX;
      this.mouse.y = clientY;
      this.mouse.active = true;

      this.targetRotY = (clientX - this.headScreenX) * 0.00055;
      this.targetRotX = -(clientY - this.headScreenY) * 0.00055;

      this.targetRotY = Math.max(-0.45, Math.min(0.45, this.targetRotY));
      this.targetRotX = Math.max(-0.35, Math.min(0.35, this.targetRotX));
    };

    window.addEventListener('mousemove', (e) => onPointerAction(e.clientX, e.clientY), { passive: true });
    window.addEventListener('touchmove', (e) => {
      if (e.touches && e.touches[0]) {
        onPointerAction(e.touches[0].clientX, e.touches[0].clientY);
      }
    }, { passive: true });
    window.addEventListener('touchstart', (e) => {
      if (e.touches && e.touches[0]) {
        onPointerAction(e.touches[0].clientX, e.touches[0].clientY);
      }
    }, { passive: true });

    window.addEventListener('mouseleave', () => {
      this.mouse.active = false;
      this.targetRotY = 0;
      this.targetRotX = 0;
    });
    window.addEventListener('touchend', () => {
      this.mouse.active = false;
      this.targetRotY = 0;
      this.targetRotX = 0;
    }, { passive: true });
  }

  initAmbientAtmosphere() {
    this.rainParticles = [];
    const rainCount = this.width <= 480 ? 320 : 550;
    for (let i = 0; i < rainCount; i++) {
      const isCyan = Math.random() > 0.45;
      this.rainParticles.push({
        x: -500 + Math.random() * 1000,
        y: -600 + Math.random() * 1200,
        z: -200 + Math.random() * 400,
        speed: 70 + Math.random() * 140,
        size: 1.4 + Math.random() * 1.8,
        alpha: 0.15 + Math.random() * 0.45,
        color: isCyan ? '0, 210, 255' : '168, 85, 247',
      });
    }

    this.ambientNodes = [];
    const nodeCount = this.width <= 480 ? 40 : 75;
    for (let i = 0; i < nodeCount; i++) {
      this.ambientNodes.push({
        x: Math.random() * window.innerWidth,
        y: Math.random() * window.innerHeight,
        vx: (Math.random() - 0.5) * 16,
        vy: (Math.random() - 0.5) * 16,
        radius: 1.2 + Math.random() * 2.2,
      });
    }
  }

  getGradientColor(normY, normX, type) {
    if (type === 'g') return '255, 255, 255';
    if (type === 'c') return '0, 242, 254';
    if (type === 's' || type === 'b') return '0, 210, 255';

    const t = Math.max(0, Math.min(1.0, (normY + 120) / 240 + (normX / 300)));

    let r, g, b;
    if (t < 0.5) {
      const k = t * 2;
      r = Math.round(0 + k * 59);
      g = Math.round(210 - k * 80);
      b = Math.round(255 - k * 9);
    } else {
      const k = (t - 0.5) * 2;
      r = Math.round(59 + k * 109);
      g = Math.round(130 - k * 45);
      b = Math.round(246 + k * 1);
    }

    return `${r}, ${g}, ${b}`;
  }

  getRawFacePoints() {
    if (typeof window !== 'undefined' && window.NEO_FACE_POINTS && Array.isArray(window.NEO_FACE_POINTS) && window.NEO_FACE_POINTS.length > 0) {
      return window.NEO_FACE_POINTS;
    }
    if (typeof NEO_FACE_POINTS !== 'undefined' && Array.isArray(NEO_FACE_POINTS) && NEO_FACE_POINTS.length > 0) {
      return NEO_FACE_POINTS;
    }
    return [];
  }

  buildParticles() {
    const rawFacePoints = this.getRawFacePoints();
    const count = rawFacePoints.length;

    if (count === 0) return;

    this.particles = [];
    const rainCols = this.streamCols;
    const pointsPerRainCol = Math.ceil(count / rainCols);

    const waveStrandsCount = this.waveStrands;
    const pointsPerStrand = Math.ceil(count / waveStrandsCount);

    for (let i = 0; i < count; i++) {
      const [fx, fy, fz, lum, ftype] = rawFacePoints[i];

      // State 0: Electrical Network Waves
      const ws = i % waveStrandsCount;
      const wp = Math.floor(i / waveStrandsCount);
      const waveNormU = (wp / pointsPerStrand) - 0.5;
      const waveStrandRatio = (ws / (waveStrandsCount - 1)) - 0.5;

      const waveBaseX = waveNormU * 540;
      const waveBaseZ = waveStrandRatio * 200 + Math.sin(waveNormU * Math.PI * 2) * 50;

      // State 1: Matrix Rain Cascade Streams
      const rc = i % rainCols;
      const rr = Math.floor(i / rainCols);
      const rainColRatio = (rc / (rainCols - 1)) - 0.5;
      const rainX = rainColRatio * 420;
      const rainY = -220 + (rr / pointsPerRainCol) * 440;
      const rainZ = Math.sin(rc * 0.4) * 45;

      let baseSize = 2.2;
      if (ftype === 'g') baseSize = 3.2;
      else if (ftype === 'c') baseSize = 2.8;
      else if (ftype === 's' || ftype === 'b') baseSize = 2.6;

      const brandColor = this.getGradientColor(fy, fx, ftype);

      this.particles.push({
        waveNormU,
        waveStrand: ws,
        waveBaseX,
        waveBaseZ,

        rainX, rainY, rainZ,
        rainCol: rc,
        rainRowY: rainY,

        fx, fy, fz, lum, ftype,

        baseSize,
        brandColor,

        x: this.headScreenX + (Math.random() - 0.5) * 400,
        y: this.headScreenY + (Math.random() - 0.5) * 400,
        vx: (Math.random() - 0.5) * 35,
        vy: (Math.random() - 0.5) * 35,

        projX: this.headScreenX,
        projY: this.headScreenY,
        projScale: 1,
        projAlpha: 1,

        swirlAngle: Math.random() * Math.PI * 2,
        swirlSpeed: 3 + Math.random() * 6,
        orbitRadius: 18 + Math.random() * 38,
      });
    }
  }

  quinticSmoothstep(t) {
    return t * t * t * (t * (t * 6 - 15) + 10);
  }

  updateMorphState(dt) {
    this.stateTimer += dt;

    if (!this.isTransitioning) {
      let holdDuration = this.holdDurationWaves;
      if (this.morphState === 1) holdDuration = this.holdDurationRain;
      else if (this.morphState === 2) holdDuration = this.holdDurationFace;

      if (this.stateTimer >= holdDuration) {
        this.isTransitioning = true;
        this.stateTimer = 0;
        this.morphFromState = this.morphState;
        this.morphToState = (this.morphState + 1) % 3;
        this.triggerVortexImpulse(180);
        this.updatePillUI(this.morphToState);
      }
    } else {
      const t = Math.min(1.0, this.stateTimer / this.transitionDuration);
      this.morphProgress = this.quinticSmoothstep(t);

      if (this.stateTimer >= this.transitionDuration) {
        this.isTransitioning = false;
        this.morphState = this.morphToState;
        this.morphProgress = 0;
        this.stateTimer = 0;
        this.updatePillUI(this.morphState);
      }
    }
  }

  triggerVortexImpulse(strength = 180) {
    for (let i = 0; i < this.particles.length; i++) {
      const p = this.particles[i];
      const angle = p.swirlAngle + this.time * p.swirlSpeed;
      p.vx += Math.cos(angle) * (strength * (0.6 + Math.random() * 0.8));
      p.vy += Math.sin(angle) * (strength * (0.6 + Math.random() * 0.8));
    }
  }

  getStateCoords(p, state) {
    if (state === 0) {
      const u = p.waveNormU;
      const s = p.waveStrand;

      const waveElevation = Math.sin(u * 9.0 + s * 0.38 + this.time * 3.6) * 65 
                          + Math.cos(u * 16.0 - this.time * 5.5 + s * 0.25) * 32
                          + Math.sin(s * 0.95 + this.time * 1.2) * 38;

      return {
        x: p.waveBaseX,
        y: waveElevation,
        z: p.waveBaseZ + Math.sin(u * 7.0 + this.time * 2.5) * 30
      };
    } else if (state === 1) {
      const cascadeFallingY = ((p.rainRowY + this.columnHeads[p.rainCol] + 440) % 440) - 220;
      return { x: p.rainX, y: cascadeFallingY, z: p.rainZ };
    } else {
      const microBreath = Math.sin(this.time * 2.0 + p.fy * 0.02) * 2.5;
      return { x: p.fx, y: p.fy + microBreath, z: p.fz };
    }
  }

  update(dt) {
    this.time += dt;

    // Retry building particles if neo_data was asynchronously parsed
    if (this.particles.length === 0) {
      this.buildParticles();
    }

    // 1. Scroll Tracking with Immediate Two-Way Visibility
    const prevSmoothScroll = this.smoothScrollY;
    this.smoothScrollY += (this.targetScrollY - this.smoothScrollY) * (8.0 * dt);
    this.scrollVelocity = (this.smoothScrollY - prevSmoothScroll) / Math.max(dt, 0.001);

    const maxScroll = Math.max(document.body.scrollHeight - window.innerHeight, 1000);
    const scrollRatio = Math.min(1.0, this.smoothScrollY / maxScroll);

    const scrollTiltX = scrollRatio * 0.55;
    const scrollYawY = Math.sin(scrollRatio * Math.PI) * 0.35;
    const scrollVerticalShift = this.smoothScrollY * -0.18;

    if (!this.statsBanner) this.statsBanner = document.querySelector('.stats-banner');
    
    if (this.targetScrollY < 180) {
      this.fadeAlpha = 1.0;
    } else if (this.statsBanner) {
      const rect = this.statsBanner.getBoundingClientRect();
      const fadeStart = window.innerHeight * 0.90;
      const fadeEnd = window.innerHeight * 0.10;
      this.fadeAlpha = Math.max(0, Math.min(1.0, (rect.top - fadeEnd) / (fadeStart - fadeEnd)));
    } else {
      this.fadeAlpha = 1.0;
    }

    if (this.fadeAlpha <= 0.001) return;

    this.updateMorphState(dt);

    // 2. Streams update
    for (let c = 0; c < this.streamCols; c++) {
      this.columnHeads[c] += this.columnSpeeds[c] * dt;
    }

    // 3. Camera Parallax
    const combinedTargetRotY = this.targetRotY + scrollYawY;
    const combinedTargetRotX = this.targetRotX - scrollTiltX;

    this.rotY += (combinedTargetRotY - this.rotY) * (7.5 * dt);
    this.rotX += (combinedTargetRotX - this.rotX) * (7.5 * dt);

    const cosY = Math.cos(this.rotY);
    const sinY = Math.sin(this.rotY);
    const cosX = Math.cos(this.rotX);
    const sinX = Math.sin(this.rotX);

    const mouseRadius = this.width <= 480 ? 120 : 160;
    const mouseRadSq = mouseRadius * mouseRadius;
    const s = this.scaleFactor;

    // 4. Atmosphere
    for (let i = 0; i < this.rainParticles.length; i++) {
      const r = this.rainParticles[i];
      r.y += r.speed * dt;
      if (r.y > 600) r.y = -600;
    }

    for (let i = 0; i < this.ambientNodes.length; i++) {
      const n = this.ambientNodes[i];
      n.x += n.vx * dt;
      n.y += n.vy * dt;
      if (n.x < 0) n.x = this.width;
      else if (n.x > this.width) n.x = 0;
      if (n.y < 0) n.y = this.height;
      else if (n.y > this.height) n.y = 0;
    }

    // 5. 3D Particles Simulation
    const isTrans = this.isTransitioning;
    const mp = this.morphProgress;

    for (let i = 0; i < this.particles.length; i++) {
      const p = this.particles[i];

      let targetX, targetY, targetZ;

      if (!isTrans) {
        const c = this.getStateCoords(p, this.morphState);
        targetX = c.x;
        targetY = c.y;
        targetZ = c.z;
      } else {
        const c1 = this.getStateCoords(p, this.morphFromState);
        const c2 = this.getStateCoords(p, this.morphToState);

        const midSpiral = Math.sin(mp * Math.PI);
        const spiralAngle = p.swirlAngle + this.time * 2.0;
        const spiralX = Math.cos(spiralAngle) * (p.orbitRadius * midSpiral);
        const spiralZ = Math.sin(spiralAngle) * (p.orbitRadius * midSpiral);

        targetX = (c1.x * (1 - mp) + c2.x * mp) + spiralX;
        targetY = (c1.y * (1 - mp) + c2.y * mp);
        targetZ = (c1.z * (1 - mp) + c2.z * mp) + spiralZ;
      }

      const x = targetX * s;
      const y = targetY * s + scrollVerticalShift;
      const z = targetZ * s;

      const x1 = x * cosY + z * sinY;
      const z1 = -x * sinY + z * cosY;

      const y2 = y * cosX - z1 * sinX;
      const z2 = y * sinX + z1 * cosX;

      const scale = this.cameraDistance / (this.cameraDistance - z2);
      p.projScale = scale;
      p.projX = this.headScreenX + x1 * scale;
      p.projY = this.headScreenY + y2 * scale;

      const depthAlpha = Math.max(0.18, Math.min(1.0, (z2 + 160) / 220));

      let baseLum = p.lum;
      const currentActiveState = isTrans ? (mp > 0.5 ? this.morphToState : this.morphFromState) : this.morphState;

      if (currentActiveState === 0) {
        const pulsePos = ((this.time * 2.2 + p.waveStrand * 0.3) % 2.0) - 1.0;
        const distToPulse = Math.abs(p.waveNormU - pulsePos);
        if (distToPulse < 0.04) {
          p.isLeader = true;
          baseLum = 1.7;
        } else if (distToPulse < 0.16) {
          p.isLeader = false;
          baseLum = 1.0 - (distToPulse / 0.16) * 0.55;
        } else {
          p.isLeader = false;
          baseLum = 0.32;
        }
      } else if (currentActiveState === 1) {
        const cascadeFallingY = ((p.rainRowY + this.columnHeads[p.rainCol] + 440) % 440) - 220;
        const streamHead = (this.columnHeads[p.rainCol] % 440) - 220;
        const distToHead = Math.abs(cascadeFallingY - streamHead);
        if (distToHead < 12) {
          p.isLeader = true;
          baseLum = 1.6;
        } else if (distToHead < 65) {
          p.isLeader = false;
          baseLum = 0.95 - (distToHead / 65) * 0.55;
        } else {
          p.isLeader = false;
          baseLum = 0.28;
        }
      } else {
        p.isLeader = false;
      }

      p.projAlpha = depthAlpha * Math.min(1.0, baseLum * 1.15 + 0.28);

      // Spring Elasticity
      const springK = 140;
      const damping = 9.5;

      const dx = p.projX - p.x;
      const dy = p.projY - p.y;
      let ax = dx * springK - p.vx * damping;
      let ay = dy * springK - p.vy * damping;

      if (this.mouse.active) {
        const mdx = p.x - this.mouse.x;
        const mdy = p.y - this.mouse.y;
        const mDistSq = mdx * mdx + mdy * mdy;

        if (mDistSq < mouseRadSq && mDistSq > 0.01) {
          const mDist = Math.sqrt(mDistSq);
          const push = (1 - mDist / mouseRadius) * (this.width <= 480 ? 380 : 500);
          ax += (mdx / mDist) * push;
          ay += (mdy / mDist) * push;
        }
      }

      p.vx += ax * dt;
      p.vy += ay * dt;
      p.x += p.vx * dt;
      p.y += p.vy * dt;
    }
  }

  render() {
    this.ctx.clearRect(0, 0, this.width, this.height);

    if (this.fadeAlpha <= 0.001) return;

    this.ctx.globalAlpha = this.fadeAlpha;

    const cosY = Math.cos(this.rotY);
    const sinY = Math.sin(this.rotY);
    const cosX = Math.cos(this.rotX);
    const sinX = Math.sin(this.rotX);

    // 1. Constellation Links
    this.ctx.lineWidth = 0.7;
    for (let i = 0; i < this.ambientNodes.length; i++) {
      const n1 = this.ambientNodes[i];
      for (let j = i + 1; j < this.ambientNodes.length; j++) {
        const n2 = this.ambientNodes[j];
        const dx = n2.x - n1.x;
        const dy = n2.y - n1.y;
        const distSq = dx * dx + dy * dy;
        if (distSq < 16900) {
          const alpha = (1 - Math.sqrt(distSq) / 130) * 0.18;
          this.ctx.strokeStyle = `rgba(0, 210, 255, ${alpha})`;
          this.ctx.beginPath();
          this.ctx.moveTo(n1.x, n1.y);
          this.ctx.lineTo(n2.x, n2.y);
          this.ctx.stroke();
        }
      }
    }

    // 2. Ambient Rain
    for (let i = 0; i < this.rainParticles.length; i++) {
      const r = this.rainParticles[i];
      const x1 = r.x * cosY + r.z * sinY;
      const z1 = -r.x * sinY + r.z * cosY;
      const y2 = r.y * cosX - z1 * sinX;
      const z2 = r.y * sinX + z1 * cosX;

      const scale = this.cameraDistance / (this.cameraDistance - z2);
      const px = this.headScreenX + x1 * scale;
      const py = this.headScreenY + y2 * scale;
      const rad = r.size * scale;

      this.ctx.fillStyle = `rgba(${r.color}, ${r.alpha})`;
      this.ctx.beginPath();
      this.ctx.arc(px, py, rad, 0, Math.PI * 2);
      this.ctx.fill();
    }

    // 3. 3D Particles
    const mainGroup = [];
    const accentGroup = [];
    const sparkGroup = [];

    const activeState = this.isTransitioning ? (this.morphProgress > 0.5 ? this.morphToState : this.morphFromState) : this.morphState;

    for (let i = 0; i < this.particles.length; i++) {
      const p = this.particles[i];

      if (activeState === 0) {
        if (p.isLeader) sparkGroup.push(p);
        else if (p.waveStrand % 2 === 0) accentGroup.push(p);
        else mainGroup.push(p);
      } else if (activeState === 1) {
        if (p.isLeader) sparkGroup.push(p);
        else if (p.rainCol % 2 === 0) accentGroup.push(p);
        else mainGroup.push(p);
      } else {
        if (p.ftype === 'g') sparkGroup.push(p);
        else if (p.ftype === 'c' || p.ftype === 's' || p.ftype === 'b') accentGroup.push(p);
        else mainGroup.push(p);
      }
    }

    // A. Main Gradient Field
    for (let i = 0; i < mainGroup.length; i++) {
      const p = mainGroup[i];
      const alpha = Math.min(1.0, p.projAlpha * 0.94);
      this.ctx.fillStyle = `rgba(${p.brandColor}, ${alpha})`;
      const r = p.baseSize * p.projScale * 0.95;
      this.ctx.beginPath();
      this.ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
      this.ctx.fill();
    }

    // B. Neon Cyan Accents
    this.ctx.shadowBlur = 16;
    this.ctx.shadowColor = '#00d2ff';
    this.ctx.fillStyle = 'rgba(0, 242, 254, 0.98)';
    this.ctx.beginPath();
    for (let i = 0; i < accentGroup.length; i++) {
      const p = accentGroup[i];
      const r = p.baseSize * p.projScale * 1.08;
      this.ctx.moveTo(p.x + r, p.y);
      this.ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
    }
    this.ctx.fill();

    // C. Sparks & Glints
    this.ctx.fillStyle = 'rgba(255, 255, 255, 1.0)';
    this.ctx.shadowColor = '#00d2ff';
    this.ctx.shadowBlur = 14;
    this.ctx.beginPath();
    for (let i = 0; i < sparkGroup.length; i++) {
      const p = sparkGroup[i];
      const r = p.baseSize * p.projScale * 1.35;
      this.ctx.moveTo(p.x + r, p.y);
      this.ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
    }
    this.ctx.fill();
    this.ctx.shadowBlur = 0;
    this.ctx.globalAlpha = 1.0;
  }

  loop(currentTime) {
    if (!this.lastTime) this.lastTime = currentTime;
    const dt = Math.min((currentTime - this.lastTime) / 1000, 0.1);
    this.lastTime = currentTime;

    this.update(dt);
    this.render();

    this.animId = requestAnimationFrame(this.loop.bind(this));
  }

  start() {
    if (!this.animId) {
      this.lastTime = performance.now();
      this.animId = requestAnimationFrame(this.loop.bind(this));
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const canvas = document.getElementById('hero-canvas');
  new InteractiveHeroParticleEngine(canvas);
});
