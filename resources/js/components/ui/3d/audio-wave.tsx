'use client';

import { useRef, useMemo } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import * as THREE from 'three';

function WaveMesh() {
  const meshRef = useRef<THREE.Mesh>(null!);
  
  // Create a grid of points
  const count = 100;
  const sep = 3;
  
  useFrame((state) => {
    if (meshRef.current) {
        const { clock } = state;
        const t = clock.getElapsedTime();
        
        const position = meshRef.current.geometry.attributes.position;
        
        for (let i = 0; i < position.count; i++) {
            const x = position.getX(i);
            const y = position.getY(i);
            
            // Calculate wave height based on x, y and time
            // Multiple sine waves for complex "voice" look
            const z = Math.sin(x * 0.5 + t) * 1.5 + 
                      Math.sin(y * 0.3 + t * 0.5) * 1.0 + 
                      Math.sin((x + y) * 0.2 + t * 0.2) * 0.5;
            
            position.setZ(i, z);
        }
        position.needsUpdate = true;
        
        // Rotate slowly
        meshRef.current.rotation.z = t * 0.05;
    }
  });

  return (
    <points ref={meshRef} rotation={[-Math.PI / 2.5, 0, 0]}>
      <planeGeometry args={[50, 50, 64, 64]} />
      <pointsMaterial 
        size={0.15} 
        color="#3b82f6" 
        transparent 
        opacity={0.6} 
        sizeAttenuation 
        map={null}
      />
    </points>
  );
}

export function AudioWave({ className = "" }: { className?: string }) {
  return (
    <div className={`absolute inset-0 w-full h-full pointer-events-none opacity-30 ${className}`}>
      <Canvas camera={{ position: [0, -10, 20], fov: 45 }}>
        <WaveMesh />
        <fog attach="fog" args={['#ffffff', 5, 40]} />
      </Canvas>
    </div>
  );
}
