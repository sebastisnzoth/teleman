'use client';

import { useRef, useMemo } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import { Points, PointMaterial } from '@react-three/drei';
import * as THREE from 'three';

function ParticleField(props: any) {
  const ref = useRef<THREE.Points>(null!);
  
  // Generate random positions
  const [positions, colors] = useMemo(() => {
    const count = 3000; // Increased count for better density
    const positions = new Float32Array(count * 3);
    const colors = new Float32Array(count * 3);
    
    for (let i = 0; i < count; i++) {
        // Spread particles across a wider area to fill screen
        const x = (Math.random() - 0.5) * 25;
        const y = (Math.random() - 0.5) * 25;
        const z = (Math.random() - 0.5) * 15;
        
        positions[i * 3] = x;
        positions[i * 3 + 1] = y;
        positions[i * 3 + 2] = z;

        // Color variation - Cyan/Blue/Purple for tech feel
        const mix = Math.random();
        if (mix < 0.33) {
            // Blue
            colors[i * 3] = 0.2;
            colors[i * 3 + 1] = 0.5;
            colors[i * 3 + 2] = 1.0;
        } else if (mix < 0.66) {
             // Cyan
            colors[i * 3] = 0.2;
            colors[i * 3 + 1] = 0.8;
            colors[i * 3 + 2] = 1.0;
        } else {
             // Purple/White accents
            colors[i * 3] = 0.6;
            colors[i * 3 + 1] = 0.6;
            colors[i * 3 + 2] = 1.0;
        }
    }
    return [positions, colors];
  }, []);

  useFrame((state, delta) => {
    if (ref.current) {
        // Slow rotation for "globe connection" feel
        ref.current.rotation.x -= delta / 30;
        ref.current.rotation.y -= delta / 25;
    }
  });

  return (
    <group rotation={[0, 0, Math.PI / 4]}>
      <Points ref={ref} positions={positions} colors={colors} stride={3} frustumCulled={false} {...props}>
        <PointMaterial
          transparent
          vertexColors
          size={0.035}
          sizeAttenuation={true}
          depthWrite={false}
          opacity={0.7}
          blending={THREE.AdditiveBlending}
        />
      </Points>
    </group>
  );
}

function Connections() {
     const lines = useMemo(() => {
        const lineCount = 30;
        const segments = [];
        for(let i=0; i<lineCount; i++) {
            const start = new THREE.Vector3((Math.random() - 0.5) * 20, (Math.random() - 0.5) * 20, (Math.random() - 0.5) * 10);
            const end = new THREE.Vector3((Math.random() - 0.5) * 20, (Math.random() - 0.5) * 20, (Math.random() - 0.5) * 10);
            // Simple line
             const points = [start, end];
             segments.push(
               <line key={i}>
                 <bufferGeometry>
                    <bufferAttribute 
                        attach="attributes-position"
                        count={2}
                        array={new Float32Array([start.x, start.y, start.z, end.x, end.y, end.z])}
                        itemSize={3}
                     />
                 </bufferGeometry>
                 <lineBasicMaterial color="#4f46e5" opacity={0.15} transparent />
               </line>
             );
        }
        return segments;
     }, []);
     return <group>{lines}</group>
}

export function NetworkBackground({ className = "" }: { className?: string }) {
  return (
    <div className={`absolute inset-0 h-full w-full pointer-events-none ${className}`}>
      <Canvas camera={{ position: [0, 0, 5], fov: 60 }} gl={{ alpha: true, antialias: true }}>
        <ParticleField />
        {/* <Connections /> // Keep it clean for now, particles are enough */}
      </Canvas>
    </div>
  );
}
